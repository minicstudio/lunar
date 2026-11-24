<?php

namespace Lunar\ProductImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Jobs\Concerns\UsesImportQueue;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Staging\ImportNormalizer;
use Lunar\ProductImport\Structuring\RowStructurer;
use Lunar\ProductImport\Taxonomy\ValueCollector;
use Lunar\ProductImport\Validation\ImportValidator;
use Throwable;

/**
 * Stages an import from its stored raw rows: normalizes every included row,
 * assigns product groups, collects and matches categories, brands, and option
 * values, and validates. It never calls the AI and never reads the file.
 */
class StageImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, UsesImportQueue;

    /**
     * Staging is not retried: staff can continue again.
     */
    public int $tries = 1;

    /**
     * Large imports take a while to stage.
     */
    public int $timeout = 900;

    public function __construct(public int $importId)
    {
        $this->useImportQueue();
    }

    public function handle(ImportNormalizer $normalizer, RowStructurer $structurer, ValueCollector $values, ImportValidator $validator): void
    {
        $import = ProductImport::query()->find($this->importId);

        if (! $import || $import->status !== ImportStatus::Staging) {
            return;
        }

        $stillStaging = fn (): bool => $import->stillHasStatus(ImportStatus::Staging);

        if (! $normalizer->normalize($import, $stillStaging) || ! $stillStaging()) {
            return;
        }

        $structurer->structure($import);

        if (! $stillStaging()) {
            return;
        }

        $valueCount = $values->collect($import);

        if (! $stillStaging()) {
            return;
        }

        $validator->validate($import);

        $import->transitionTo(
            $valueCount > 0 ? ImportStatus::ReviewingValues : ImportStatus::PreviewReady,
            ImportStatus::Staging,
            ['failure_message' => null],
        );
    }

    /**
     * Return to the mapping step with a message when staging fails.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Product import: staging failed.', [
            'product_import_id' => $this->importId,
            'exception' => $exception,
        ]);

        ProductImport::query()->find($this->importId)?->transitionTo(ImportStatus::AwaitingMapping, ImportStatus::Staging, [
            'failure_message' => __('lunarpanel.product-import::errors.staging_failed'),
        ]);
    }
}
