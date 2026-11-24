<?php

namespace Lunar\ProductImport\Analysis;

use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Reading\HeaderDetector;
use Lunar\ProductImport\Reading\ReadRow;

/**
 * Builds a sheet's header row, data row count, and column profiles from its
 * stored raw rows, without reading the file.
 */
class SheetProfileBuilder
{
    public function __construct(
        protected HeaderDetector $headerDetector,
        protected ColumnProfiler $profiler,
    ) {}

    /**
     * Detect the header row unless one is given, then profile the columns.
     * A sheet without data rows is skipped.
     */
    public function build(ProductImportSheet $sheet, ?int $headerRow = null): ProductImportSheet
    {
        $headerRow ??= $this->headerDetector->detect($this->firstRows($sheet));

        $headerCells = $sheet->rows()->where('row_number', $headerRow)->value('raw') ?? [];

        $dataRows = $sheet->rows()->where('row_number', '>', $headerRow);
        $rowCount = (clone $dataRows)->count();

        $columns = $this->profiler->profile(
            $headerCells,
            (clone $dataRows)->select(['id', 'row_number', 'raw', 'typed'])->lazyById(1000)
                ->map(fn (ProductImportRow $row): ReadRow => new ReadRow($row->row_number, $row->raw, $row->typed ?? [])),
        );

        $sheet->forceFill([
            'header_row' => $headerRow,
            'row_count' => $rowCount,
            'columns' => $columns,
            'skipped' => $rowCount === 0 ? true : $sheet->skipped,
        ])->save();

        return $sheet;
    }

    /**
     * The first stored rows, keyed by row number.
     *
     * @return array<int, array<int, ?string>>
     */
    protected function firstRows(ProductImportSheet $sheet): array
    {
        return $sheet->rows()
            ->orderBy('row_number')
            ->limit(HeaderDetector::CANDIDATE_ROWS)
            ->get(['row_number', 'raw'])
            ->mapWithKeys(fn (ProductImportRow $row): array => [$row->row_number => $row->raw])
            ->all();
    }
}
