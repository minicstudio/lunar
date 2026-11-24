<?php

namespace Lunar\ProductImport\Reading;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reads only one sheet of a workbook, by position.
 */
class SelectedSheetImport implements Import, WithChunkReading, WithMultipleSheets
{
    public function __construct(
        protected int $sheetIndex,
        protected SheetRowsImport $sheetImport,
    ) {}

    /**
     * @return array<int, SheetRowsImport>
     */
    public function sheets(): array
    {
        return [$this->sheetIndex => $this->sheetImport];
    }

    public function chunkSize(): int
    {
        return $this->sheetImport->chunkSize();
    }
}
