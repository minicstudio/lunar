<?php

namespace Lunar\Tests\ProductImport\Support;

use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * A shop export grouped by parent whose "grouped" row type is not imported,
 * for the tests of including rows that their type skips.
 */
class GroupedRows
{
    /**
     * The "grouped" row "Set vară", without SKU and price.
     *
     * @var list<?string>
     */
    public const SET_VARA = ['90', 'grouped', '', '', 'Set vară', '', '5', ''];

    /**
     * A staged import, ready for the preview, of a sheet with ID, type,
     * parent, SKU, name, price, stock, and brand. Row 2 is "Set vară" unless
     * the rows give another row 2.
     *
     * @param  array<int, list<?string>>  $rows  Data rows keyed by row number (header is row 1).
     */
    public static function import(int $productTypeId, array $rows = [], ?ImportOptions $options = null, string $locale = 'ro'): ProductImport
    {
        $import = ImportBuilder::import(ImportStatus::Staging, $options !== null ? ['options' => $options->toArray()] : []);

        $rows += [2 => self::SET_VARA];
        ksort($rows);

        self::map(ImportBuilder::sheet($import, 'export', [1 => ['ID', 'Type', 'Parent', 'SKU', 'Name', 'Price', 'Stock', 'Brand']] + $rows), $productTypeId, $locale);

        ImportBuilder::stage($import);
        $import->update(['status' => ImportStatus::PreviewReady]);

        return $import;
    }

    /**
     * Map the sheet, with its stock column or without it.
     */
    public static function map(ProductImportSheet $sheet, int $productTypeId, string $locale = 'ro', bool $withStock = false): ProductImportSheet
    {
        return ImportBuilder::map($sheet, array_filter([
            0 => 'source.id',
            1 => ['source.type', null, ColumnFormat::meanings(['simple' => 'single', 'grouped' => 'skip'])],
            2 => 'source.parent',
            3 => 'variant.sku',
            4 => ['product.name', $locale],
            5 => 'variant.price',
            6 => $withStock ? 'variant.stock' : null,
            7 => 'product.brand',
        ]), $productTypeId, strategy: GroupingStrategy::ByParent);
    }

    /**
     * The import's row with the given row number.
     */
    public static function row(ProductImport $import, int $number): ProductImportRow
    {
        return ProductImportRow::includedIn($import)->where('row_number', $number)->sole();
    }
}
