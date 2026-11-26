<?php

namespace Lunar\ProductImport\Reading;

/**
 * A sheet's name and size, read without loading its cells.
 */
final readonly class SheetInfo
{
    public function __construct(
        public int $index,
        public string $name,
        public int $totalRows,
        public int $totalColumns,
    ) {}
}
