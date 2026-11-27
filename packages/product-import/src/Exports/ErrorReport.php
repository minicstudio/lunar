<?php

namespace Lunar\ProductImport\Exports;

use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A CSV of the rows that failed, the rows skipped because of errors, and the
 * rows with failed images: sheet, row number, the original cells under their
 * headers, and the messages.
 */
class ErrorReport
{
    /**
     * Stream the report as a download.
     */
    public function download(ProductImport $import): StreamedResponse
    {
        $this->ensureAvailable($import);

        $name = Str::slug(pathinfo((string) $import->files()->value('original_name'), PATHINFO_FILENAME) ?: 'import').'-errors.csv';

        return response()->streamDownload(function () use ($import): void {
            $handle = fopen('php://output', 'w');
            $this->write($import, $handle);
            fclose($handle);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Write the report to a stream.
     *
     * @param  resource  $handle
     */
    public function write(ProductImport $import, $handle): void
    {
        $this->ensureAvailable($import);

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($this->lines($import) as $line) {
            fputcsv($handle, $line, ',', '"', '\\');
        }
    }

    /**
     * The header line, then one line per reported row.
     *
     * @return Generator<int, list<string>>
     */
    public function lines(ProductImport $import): Generator
    {
        $sheets = $import->sheets()->get()->keyBy('id');
        $headers = $this->headers($sheets->all());

        yield [
            __('lunarpanel.product-import::report.sheet'),
            __('lunarpanel.product-import::report.row'),
            ...$headers,
            __('lunarpanel.product-import::report.messages'),
        ];

        $positions = array_flip($headers);

        foreach ($this->reportedRows($import)->lazyById(500) as $row) {
            /** @var ProductImportRow $row */
            if ($row->status === RowStatus::Skipped && ! $row->hasErrors()) {
                continue;
            }

            $sheet = $sheets[$row->product_import_sheet_id];
            $cells = array_fill(0, count($headers), '');

            foreach ($sheet->columns ?? [] as $column) {
                $value = $row->raw[(int) $column['index']] ?? null;

                if ($value !== null) {
                    $cells[$positions[$column['header']]] = (string) $value;
                }
            }

            $messages = collect($row->messages ?? [])
                ->whereIn('level', ['error', 'warning'])
                ->pluck('message')
                ->implode(' | ');

            yield [$sheet->name, (string) $row->row_number, ...$cells, $messages];
        }
    }

    /**
     * The failed and skipped rows, and the rows with failed images; skipped
     * rows without errors are left out while writing.
     */
    public function reportedRows(ProductImport $import): Builder
    {
        return ProductImportRow::includedIn($import)
            ->where(fn (Builder $query) => $query
                ->whereIn('status', [RowStatus::Failed, RowStatus::Skipped])
                ->orWhereIn('id', ProductImportImage::query()
                    ->where('product_import_id', $import->id)
                    ->where('status', ImageStatus::Failed)
                    ->whereNotNull('product_import_row_id')
                    ->select('product_import_row_id')))
            ->orderBy('product_import_sheet_id')
            ->orderBy('row_number');
    }

    /**
     * Refuse once the row data was pruned.
     */
    protected function ensureAvailable(ProductImport $import): void
    {
        if ($import->isPruned()) {
            throw ImportActionRefused::because('pruned');
        }
    }

    /**
     * Every sheet's headers, in sheet and column order, each once.
     *
     * @param  array<int, ProductImportSheet>  $sheets
     * @return list<string>
     */
    protected function headers(array $sheets): array
    {
        $headers = [];

        foreach ($sheets as $sheet) {
            foreach ($sheet->columns ?? [] as $column) {
                $headers[(string) $column['header']] = true;
            }
        }

        return array_map('strval', array_keys($headers));
    }
}
