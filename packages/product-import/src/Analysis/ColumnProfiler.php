<?php

namespace Lunar\ProductImport\Analysis;

use Illuminate\Support\Str;
use Lunar\ProductImport\Reading\ReadRow;

/**
 * Computes each column's profile over a sheet's data rows: its header, the
 * share of filled cells, the share of distinct values, and distinct samples.
 * The profile does not classify the column or say which separators it uses:
 * the AI reads what a column holds from its header and samples, and the
 * target that staff confirm decides how the column is read.
 */
class ColumnProfiler
{
    /**
     * Profile every column of the header and the data rows. A row is its
     * cells keyed by column index, or a `ReadRow` that also says which cells
     * the spreadsheet stores as numbers or dates.
     *
     * @param  array<int, ?string>  $headerCells  The header row's cells keyed by column index.
     * @param  iterable<array<int, ?string>|ReadRow>  $rows  The data rows.
     * @return list<array{index: int, header: string, fill_rate: float, distinct_share: float, samples: list<string>, samples_typed: bool}>
     */
    public function profile(array $headerCells, iterable $rows): array
    {
        $rowCount = 0;
        $lastIndex = $headerCells === [] ? -1 : max(array_keys($headerCells));

        /** @var array<int, array<string, int>> $distinct */
        $distinct = [];
        /** @var array<int, array<string, int>> $distinctText */
        $distinctText = [];
        /** @var array<int, int> $filled */
        $filled = [];

        foreach ($rows as $row) {
            $rowCount++;
            [$cells, $typed] = $row instanceof ReadRow ? [$row->cells, $row->typed] : [$row, []];

            foreach ($cells as $index => $value) {
                $value = Str::trim((string) $value);

                if ($value === '') {
                    continue;
                }

                $index = (int) $index;

                $lastIndex = max($lastIndex, $index);
                $filled[$index] = ($filled[$index] ?? 0) + 1;
                $distinct[$index][$value] = 1;

                if (! in_array($index, $typed, true)) {
                    $distinctText[$index][$value] = 1;
                }
            }
        }

        $profiles = [];

        for ($index = 0; $index <= $lastIndex; $index++) {
            $filledCount = $filled[$index] ?? 0;

            $profiles[] = [
                'index' => $index,
                'header' => $this->header($headerCells[$index] ?? null, $index),
                'fill_rate' => $rowCount > 0 ? round($filledCount / $rowCount, 4) : 0.0,
                'distinct_share' => $filledCount > 0 ? round(count($distinct[$index] ?? []) / $filledCount, 4) : 0.0,
                'samples' => $this->samples($distinct[$index] ?? [], $distinctText[$index] ?? []),
                'samples_typed' => $filledCount > 0 && ($distinctText[$index] ?? []) === [],
            ];
        }

        return $profiles;
    }

    /**
     * The header text, or a positional name for an empty header.
     */
    public function header(?string $header, int $index): string
    {
        $header = Str::trim((string) $header);

        return $header !== ''
            ? $header
            : __('lunarpanel.product-import::import.column_fallback', ['number' => $index + 1]);
    }

    /**
     * Up to `ai.sample_size` distinct samples of the column, taken from the
     * cells stored as text when the column has any, because only those are
     * read with the column's format.
     *
     * @param  array<string, int>  $distinct
     * @param  array<string, int>  $distinctText
     * @return list<string>
     */
    protected function samples(array $distinct, array $distinctText): array
    {
        $sampleLength = (int) config('lunar.product-import.ai.sample_length', 100);
        $values = $distinctText !== [] ? $distinctText : $distinct;

        return array_map(
            fn (string|int $value): string => Str::limit((string) $value, $sampleLength, ''),
            array_slice(array_keys($values), 0, (int) config('lunar.product-import.ai.sample_size', 30)),
        );
    }
}
