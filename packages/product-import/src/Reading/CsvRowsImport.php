<?php

namespace Lunar\ProductImport\Reading;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * The import of a CSV file. Every cell is bound as text, so a CSV value is
 * stored exactly as written and is never read as a number or a date.
 */
class CsvRowsImport extends SheetRowsImport implements WithCustomValueBinder
{
    /**
     * Bind every value as a string.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        $cell->setValueExplicit($value === null ? null : (string) $value, $value === null ? DataType::TYPE_NULL : DataType::TYPE_STRING);

        return true;
    }
}
