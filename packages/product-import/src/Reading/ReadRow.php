<?php

namespace Lunar\ProductImport\Reading;

/**
 * One non-empty row as read from the file: its row number in the sheet, its
 * cells keyed by zero-based column index, and the indexes of the cells the
 * spreadsheet stores as numbers or dates.
 */
final readonly class ReadRow
{
    /**
     * @param  array<int, string>  $cells
     * @param  list<int>  $typed
     */
    public function __construct(
        public int $rowNumber,
        public array $cells,
        public array $typed = [],
    ) {}
}
