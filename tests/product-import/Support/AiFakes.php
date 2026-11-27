<?php

namespace Lunar\Tests\ProductImport\Support;

use Closure;
use Lunar\ProductImport\Jobs\AnalyzeSheet;
use Throwable;

/**
 * Configures the import AI for tests and builds fake answers.
 */
class AiFakes
{
    /**
     * Configure an OpenAI provider with a shared key (no network is used:
     * tests fake the agent).
     */
    public static function configure(?string $ownKey = null): void
    {
        config([
            'lunar.product-import.ai.provider' => 'openai',
            'lunar.product-import.ai.model' => null,
            'lunar.product-import.ai.key' => $ownKey,
            'lunar.product-import.ai.url' => null,
            'ai.providers.openai.key' => 'sk-shared-test-key',
        ]);
    }

    /**
     * An answer mapping the given columns.
     *
     * @param  list<array{0: int, 1: string, 2?: float, 3?: ?string, 4?: ?string, 5?: ?array{decimal_separator: string, thousands_separator: string}, 6?: ?string, 7?: array{list_separator?: ?string, path_separator?: ?string, item_separator?: ?string}}>  $columns  [index, target, confidence, locale, option name, number format, date format, separators]
     * @param  array{strategy: string, column?: ?int, confidence?: float}  $grouping
     * @param  array{weight_unit?: ?string, length_unit?: ?string}  $units
     * @return array<string, mixed>
     */
    public static function answer(array $columns, array $grouping = ['strategy' => 'per_row'], array $units = []): array
    {
        return [
            'columns' => array_map(fn (array $column): array => [
                'index' => $column[0],
                'target' => $column[1],
                'confidence' => $column[2] ?? 0.95,
                'locale' => $column[3] ?? null,
                'option_name' => $column[4] ?? null,
                'number_format' => $column[5] ?? null,
                'date_format' => $column[6] ?? null,
                'list_separator' => $column[7]['list_separator'] ?? null,
                'path_separator' => $column[7]['path_separator'] ?? null,
                'item_separator' => $column[7]['item_separator'] ?? null,
            ], $columns),
            'grouping' => [
                'strategy' => $grouping['strategy'],
                'column' => $grouping['column'] ?? null,
                'confidence' => $grouping['confidence'] ?? 0.9,
            ],
            'units' => ['weight_unit' => $units['weight_unit'] ?? null, 'length_unit' => $units['length_unit'] ?? null],
        ];
    }

    /**
     * A fake response closure that returns the next answer or throws the next
     * exception on each prompt. Fake arrays do not move past a response
     * closure that throws, so failures in a row need this.
     *
     * @param  list<array<string, mixed>|Throwable>  $responses
     */
    public static function sequence(array $responses): Closure
    {
        $index = 0;

        return function () use ($responses, &$index): array {
            $response = $responses[min($index, count($responses) - 1)];
            $index++;

            if ($response instanceof Throwable) {
                throw $response;
            }

            return $response;
        };
    }

    /**
     * Run an analysis job the way a queue worker does: up to its tries, with
     * the attempt count, calling `failed()` when it gives up.
     */
    public static function runLikeWorker(int $sheetId): AnalyzeSheet
    {
        $job = new AnalyzeSheet($sheetId);

        for ($attempt = 1; $attempt <= $job->tries; $attempt++) {
            $job->withFakeQueueInteractions();
            $job->job->attempts = $attempt;

            try {
                app()->call([$job, 'handle']);
            } catch (Throwable $exception) {
                if ($attempt === $job->tries) {
                    $job->failed($exception);
                }

                continue;
            }

            if ($job->job->hasFailed()) {
                $job->failed($job->job->failedWith);
            }

            break;
        }

        return $job;
    }
}
