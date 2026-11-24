<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ExistingVariants;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\RowAction;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    $this->productType = ProductType::factory()->create();
    $this->import = ImportBuilder::import(ImportStatus::Staging);
});

/**
 * A "Produse" sheet with SKU, group, name, price, stock, size, and color columns.
 *
 * @param  array<int, list<?string>>  $rows  Data rows keyed by row number (header is row 1).
 */
function produseSheet(ProductImport $import, int $productTypeId, array $rows, string $name = 'Produse'): ProductImportSheet
{
    $sheet = ImportBuilder::sheet($import, $name, [1 => ['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Stoc', 'Marime', 'Culoare', 'EAN', 'Categorie']] + $rows);

    return ImportBuilder::map($sheet, [
        0 => 'variant.sku',
        1 => 'group_key',
        2 => ['product.name', 'ro'],
        3 => 'variant.price',
        4 => 'variant.stock',
        5 => 'variant.option',
        6 => 'variant.option',
        7 => 'variant.ean',
        8 => 'product.category',
    ], $productTypeId, 1);
}

function stagedRow(ProductImportSheet $sheet, int $rowNumber): ProductImportRow
{
    return ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', $rowNumber)->firstOrFail();
}

/**
 * The messages of a row at a level.
 *
 * @return list<string>
 */
function rowMessages(ProductImportRow $row, string $level = 'error'): array
{
    return array_values(array_map(
        fn (array $message) => $message['message'],
        array_filter($row->messages ?? [], fn (array $message) => $message['level'] === $level),
    ));
}

function existingVariant(string $sku, ?Product $product = null): ProductVariant
{
    return ProductVariant::factory()->for($product ?? Product::factory()->create(), 'product')->create(['sku' => $sku]);
}

test('a new variant without a price has the error missing price', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-L-BLU', 'TB-001', 'Tricou basic', '', '5', 'L', 'Albastru', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    $row = stagedRow($sheet, 2);

    expect($row->status)->toBe(RowStatus::Error)
        ->and($row->action)->toBe(RowAction::Create)
        ->and(rowMessages($row))->toBe(['Missing price']);
});

test('a row without the match key has the error missing sku', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['', 'TB-001', 'Tricou basic', '49,90', '5', 'L', 'Albastru', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2)))->toBe(['Missing SKU']);
});

test('text in the stock column is not a whole number', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', 'la comanda', 'S', 'Rosu', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2)))->toBe(['The stock "la comanda" is not a whole number in the column format with a decimal comma and a dot thousands separator']);
});

test('a stock with a fraction is not a whole number', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '2,5', 'S', 'Rosu', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2)))->toBe(['The stock "2,5" is not a whole number']);
});

test('a weight in another format is an error naming the expected format', function () {
    $sheet = ImportBuilder::sheet($this->import, 'Produse', [
        ['Cod', 'Denumire', 'Pret', 'Greutate'],
        ['TB-001-S', 'Tricou basic', '49,90', '1,25'],
    ]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => ['variant.weight', null, ColumnFormat::number('.')]], $this->productType->id, weightUnit: 'kg');

    ImportBuilder::stage($this->import);

    $row = stagedRow($sheet, 2);

    expect(rowMessages($row))->toBe(['The weight "1,25" does not match the column format with a decimal dot and no thousands separator'])
        ->and($row->effectiveRow()->get('variant.weight'))->toBeNull();
});

test('a negative price and a price that is not a number are errors', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-S', 'TB-001-S', 'Tricou basic', '-5', '1', 'S', 'Rosu', '5941234567890', 'Femei'],
        3 => ['TB-001-M', 'TB-001-M', 'Tricou basic', 'gratis', '1', 'M', 'Rosu', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2)))->toBe(['The price "-5" cannot be negative'])
        ->and(rowMessages(stagedRow($sheet, 3)))->toBe(['The price "gratis" does not match the column format with a decimal comma and a dot thousands separator']);
});

test('a sku on two sheets is a duplicate on both rows', function () {
    $produse = produseSheet($this->import, $this->productType->id, [
        12 => ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '5', 'S', 'Rosu', '5941234567890', 'Femei'],
    ]);
    $outlet = produseSheet($this->import, $this->productType->id, [
        5 => ['TB-001-S', 'TB-001', 'Tricou basic', '29,90', '2', 'S', 'Rosu', '5941234567890', 'Outlet'],
    ], 'Outlet');

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($produse, 12)))->toBe(['The SKU "TB-001-S" also appears on Outlet row 5'])
        ->and(rowMessages(stagedRow($outlet, 5)))->toBe(['The SKU "TB-001-S" also appears on Produse row 12']);
});

test('a skipped duplicate no longer counts', function () {
    $produse = produseSheet($this->import, $this->productType->id, [
        12 => ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '5', 'S', 'Rosu', '5941234567890', 'Femei'],
    ]);
    $outlet = produseSheet($this->import, $this->productType->id, [
        5 => ['TB-001-S', 'TB-001', 'Tricou basic', '29,90', '2', 'S', 'Rosu', '5941234567890', 'Outlet'],
    ], 'Outlet');

    ImportBuilder::stage($this->import);
    stagedRow($outlet, 5)->update(['skip_reason' => RowSkipReason::Staff]);
    ImportBuilder::stage($this->import);

    expect(stagedRow($produse, 12)->status)->toBe(RowStatus::Valid)
        ->and(stagedRow($outlet, 5)->status)->toBe(RowStatus::Skipped);
});

test('the same size and color twice in one product is repeated', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-M-RED', 'TB-001', 'Tricou basic', '49,90', '5', 'M', 'Red', '5941234567890', 'Femei'],
        3 => ['TB-001-M-RED2', 'TB-001', 'Tricou basic', '49,90', '5', 'M', 'Red', '5941234567890', 'Femei'],
        4 => ['TB-001-L-RED', 'TB-001', 'Tricou basic', '49,90', '5', 'L', 'Red', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2)))->toBe(['The option combination M / Red is repeated'])
        ->and(rowMessages(stagedRow($sheet, 3)))->toBe(['The option combination M / Red is repeated'])
        ->and(stagedRow($sheet, 4)->status)->toBe(RowStatus::Valid);
});

test('several rows of one product without an option value are errors', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-A', 'TB-001', 'Tricou basic', '49,90', '5', '', '', '5941234567890', 'Femei'],
        3 => ['TB-001-B', 'TB-001', 'Tricou basic', '49,90', '5', 'M', 'Red', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2)))->toBe(['This product has several rows, so each row needs an option value'])
        ->and(stagedRow($sheet, 3)->status)->toBe(RowStatus::Valid);
});

test('a group of several rows without an option column has the error on every row', function () {
    $sheet = ImportBuilder::sheet($this->import, 'Produse', [['Cod', 'Cod parinte', 'Denumire', 'Pret'], ['A-1', 'A', 'Tricou', '10'], ['A-2', 'A', 'Tricou', '10']]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => 'group_key', 2 => ['product.name', 'ro'], 3 => 'variant.price'], $this->productType->id, 1);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2)))->toBe(['This product has several rows, but no option column is mapped'])
        ->and(rowMessages(stagedRow($sheet, 3)))->toBe(['This product has several rows, but no option column is mapped']);
});

test('a group matching variants of two existing products is a conflict', function () {
    existingVariant('GP-M');
    existingVariant('GP-L');

    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['GP-M', 'Geacă puf', 'Geacă puf', '399', '2', 'M', 'Negru', '5941234567890', 'Femei'],
        3 => ['GP-L', 'Geacă puf', 'Geacă puf', '399', '2', 'L', 'Negru', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2)))->toBe(['The variants belong to 2 different existing products'])
        ->and(rowMessages(stagedRow($sheet, 3)))->toBe(['The variants belong to 2 different existing products']);
});

test('a new size of an existing product becomes a new variant of it', function () {
    $product = Product::factory()->create(['id' => 1842]);
    $existing = existingVariant('HO-114-M', $product);

    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['HO-114-M', 'HO-114', 'Hanorac', '199', '3', 'M', 'Gri', '5941234567890', 'Barbati'],
        3 => ['HO-114-XL', 'HO-114', 'Hanorac', '199', '3', 'XL', 'Gri', '5941234567890', 'Barbati'],
    ]);

    ImportBuilder::stage($this->import);

    $matched = stagedRow($sheet, 2);
    $new = stagedRow($sheet, 3);

    expect($matched->action)->toBe(RowAction::Update)
        ->and($matched->matched_variant_id)->toBe($existing->id)
        ->and($matched->matched_product_id)->toBe(1842)
        ->and($new->action)->toBe(RowAction::Create)
        ->and($new->matched_product_id)->toBe(1842)
        ->and($new->matched_variant_id)->toBeNull()
        ->and($new->status)->toBe(RowStatus::Valid);
});

test('a row matching a variant of a trashed product is an update that restores it', function () {
    $product = Product::factory()->create();
    existingVariant('OLD-1', $product);
    $product->delete();

    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['OLD-1', 'OLD', 'Produs vechi', '10', '1', 'S', 'Alb', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    $row = stagedRow($sheet, 2);

    expect($row->action)->toBe(RowAction::Update)
        ->and($row->matched_product_id)->toBe($product->id)
        ->and(rowMessages($row, 'info'))->toBe(["Product #{$product->id} is in the trash and will be restored"]);
});

test('an empty price on an update is a warning that the price stays unchanged', function () {
    existingVariant('TB-001-S');

    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-S', 'TB-001', 'Tricou basic', '', '5', 'S', 'Rosu', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    $row = stagedRow($sheet, 2);

    expect($row->status)->toBe(RowStatus::Warning)
        ->and(rowMessages($row))->toBe([])
        ->and(rowMessages($row, 'warning'))->toBe(['The price stays unchanged']);
});

test('stock 0, no category, and an unusual ean are warnings', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '0', 'S', 'Rosu', '12345', ''],
    ]);

    ImportBuilder::stage($this->import);

    $row = stagedRow($sheet, 2);

    expect($row->status)->toBe(RowStatus::Warning)
        ->and(rowMessages($row, 'warning'))->toBe([
            'Stock is 0',
            'The EAN "12345" does not have 8, 12, 13, or 14 digits',
            'The product has no category',
        ]);
});

test('a new product needs a name in the default language', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-S', 'TB-001', '', '49,90', '5', 'S', 'Rosu', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2)))->toBe(['Missing product name in the default language (ro)']);
});

test('existing variants set to be skipped are marked as skipped', function () {
    existingVariant('TB-001-S');

    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '5', 'S', 'Rosu', '5941234567890', 'Femei'],
    ]);

    ImportBuilder::stage($this->import, new ImportOptions(existingVariants: ExistingVariants::Skip));

    $row = stagedRow($sheet, 2);

    expect($row->status)->toBe(RowStatus::Skipped)
        ->and($row->skip_reason)->toBe(RowSkipReason::Existing)
        ->and(rowMessages($row, 'info'))->toBe(['The variant exists and stays unchanged']);
});

test('an unknown status of a new product is a warning when new products take the status column', function () {
    $sheet = ImportBuilder::sheet($this->import, 'Produse', [['Cod', 'Denumire', 'Pret', 'Published'], ['TB-1', 'Tricou', '49,90', 'maybe'], ['TB-2', 'Rochie', '99', '1']]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'product.status'], $this->productType->id);

    ImportBuilder::stage($this->import, new ImportOptions(newProductStatus: NewProductStatus::FromColumn));

    $row = stagedRow($sheet, 2);

    expect($row->status)->toBe(RowStatus::Warning)
        ->and(rowMessages($row))->toBe([])
        ->and(rowMessages($row, 'warning'))->toBe(['The status "maybe" is not recognized, so the new product is created as a draft'])
        ->and(stagedRow($sheet, 3)->status)->toBe(RowStatus::Valid);
});

test('an unknown status gives no message when new products are drafts', function () {
    $sheet = ImportBuilder::sheet($this->import, 'Produse', [['Cod', 'Denumire', 'Pret', 'Published'], ['TB-1', 'Tricou', '49,90', 'maybe']]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'product.status'], $this->productType->id);

    ImportBuilder::stage($this->import);

    expect(stagedRow($sheet, 2)->status)->toBe(RowStatus::Valid);
});

test('a valid row of a new product is valid', function () {
    $sheet = produseSheet($this->import, $this->productType->id, [
        2 => ['TB-001-S', 'TB-001', 'Tricou basic', '49,90', '5', 'S', 'Rosu', '5941234567890', 'Femei > Tricouri'],
    ]);

    ImportBuilder::stage($this->import);

    $row = stagedRow($sheet, 2);

    expect($row->status)->toBe(RowStatus::Valid)
        ->and($row->messages)->toBe([])
        ->and($row->match_value)->toBe('TB-001-S')
        ->and($row->group_key)->toBe($sheet->id.':TB-001');
});

/**
 * A sheet grouped by parent with an option pair, a sale, and up-sells.
 *
 * @param  array<int, list<?string>>  $rows  Data rows keyed by row number (header is row 1).
 */
function variableSheet(ProductImport $import, int $productTypeId, array $rows): ProductImportSheet
{
    $sheet = ImportBuilder::sheet($import, 'wc-product-export', [1 => ['ID', 'Parent', 'SKU', 'Name', 'Regular price', 'Stock', 'Attribute 1 name', 'Attribute 1 value(s)', 'Sale price', 'Date sale price ends', 'Upsells']] + $rows);

    return ImportBuilder::map($sheet, [
        0 => 'source.id', 1 => 'source.parent', 2 => 'variant.sku', 3 => ['product.name', 'ro'], 4 => 'variant.price', 5 => 'variant.stock',
        6 => ['variant.option_name', 1], 7 => ['variant.option_value', 1], 8 => 'variant.sale_price', 9 => 'variant.sale_ends', 10 => 'product.upsells',
    ], $productTypeId, strategy: GroupingStrategy::ByParent);
}

test('a variable product row without sku and price has no error, and a product row without variations has the error', function () {
    $sheet = variableSheet($this->import, $this->productType->id, [
        2 => ['57', '', '', 'Rochie vară', '', '', 'Size', 'S, M', '', '', ''],
        3 => ['58', 'id:57', 'RV-S', '', '199,00', '3', 'Size', 'S', '', '', ''],
        4 => ['70', '', '', 'Geacă puf', '', '', 'Size', 'S', '', '', ''],
        5 => ['71', 'id:70', 'GP-S', '', '399,00', '3', 'Size', 'S', '', '', ''],
        6 => ['72', 'id:71', 'GP-S-2', '', '399,00', '3', 'Size', 'M', '', '', ''],
    ]);

    $sheet2 = ImportBuilder::sheet($this->import, 'typed', [['ID', 'Type', 'Name'], ['80', 'variable', 'Rochie fără variante']]);
    ImportBuilder::map($sheet2, [
        0 => 'source.id', 1 => ['source.type', null, ColumnFormat::meanings(['variable' => 'parent'])], 2 => ['product.name', 'ro'],
    ], $this->productType->id);

    ImportBuilder::stage($this->import);

    expect(stagedRow($sheet, 2)->messages)->toBe([])
        ->and(stagedRow($sheet, 2)->status)->toBe(RowStatus::Valid)
        ->and(stagedRow($sheet, 2)->match_value)->toBeNull()
        ->and(rowMessages(stagedRow($sheet2, 2)))->toBe(['The product has no variants in the import']);
});

test('an empty size on a variation is an error', function () {
    $sheet = variableSheet($this->import, $this->productType->id, [
        2 => ['57', '', '', 'Rochie vară', '', '', 'Size', 'S, M', '', '', ''],
        3 => ['58', 'id:57', 'RV-S', '', '199,00', '3', 'Size', 'S', '', '', ''],
        4 => ['59', 'id:57', 'RV-ANY', '', '199,00', '3', 'Size', '', '', '', ''],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 4)))->toContain('The variant needs one value for "Size"')
        ->and(rowMessages(stagedRow($sheet, 3)))->toBe([]);
});

test('a sale ending at christmas warns, and an up-sell outside the file warns', function () {
    $this->travelTo(now()->setDate(2026, 11, 23));

    $sheet = variableSheet($this->import, $this->productType->id, [
        2 => ['57', '', '', 'Rochie vară', '', '', 'Size', 'S', '', '', 'id:310, id:57'],
        3 => ['58', 'id:57', 'RV-S', '', '199,00', '3', 'Size', 'S', '149,00', '2026-12-24 23:59:59', ''],
        4 => ['60', '', 'TB-1', 'Tricou', '199,00', '3', '', '', '149,00', '2026-11-01 23:59:59', ''],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 3), 'warning'))->toBe(['The sale price 149.00 stays after 24 December 2026, because a sale does not end by itself'])
        ->and(rowMessages(stagedRow($sheet, 2), 'warning'))->toBe(['The up-sell "id:310" was not found'])
        ->and(rowMessages(stagedRow($sheet, 4), 'warning'))->toBe(['The sale does not apply today or is not below the regular price, so the regular price is used']);
});

test('the stock of the parent warns on its variations without stock', function () {
    $sheet = variableSheet($this->import, $this->productType->id, [
        2 => ['57', '', '', 'Rochie vară', '', '10', 'Size', 'S, M', '', '', ''],
        3 => ['58', 'id:57', 'RV-S', '', '199,00', '', 'Size', 'S', '', '', ''],
        4 => ['59', 'id:57', 'RV-M', '', '199,00', '4', 'Size', 'M', '', '', ''],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 3), 'warning'))->toBe(["The product's stock of 10 is not split among its variants, because each variant keeps its own stock"])
        ->and(rowMessages(stagedRow($sheet, 4), 'warning'))->toBe([]);
});

/**
 * A sheet of new products with a Romanian URL slug column.
 *
 * @param  array<int, list<?string>>  $rows  Data rows keyed by row number: SKU, name, price, slug.
 */
function slugSheet(ProductImport $import, int $productTypeId, array $rows): ProductImportSheet
{
    $sheet = ImportBuilder::sheet($import, 'Produse', [1 => ['Cod', 'Denumire', 'Pret', 'URL key']] + $rows);

    return ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => ['product.slug', 'ro']], $productTypeId);
}

/**
 * A product with the given Romanian URL as its default.
 */
function productWithUrl(string $slug, ?string $sku = null): Product
{
    $product = Product::factory()->create();
    $product->urls()->create(['language_id' => Language::getDefault()->id, 'slug' => $slug, 'default' => true]);

    if ($sku !== null) {
        existingVariant($sku, $product);
    }

    return $product;
}

test('a slug cell that gives no slug is a warning naming the field and the raw value', function () {
    $sheet = slugSheet($this->import, $this->productType->id, [2 => ['RV-1', 'Rochie vară', '199', '!!!']]);

    ImportBuilder::stage($this->import);

    $row = stagedRow($sheet, 2);

    expect($row->status)->toBe(RowStatus::Warning)
        ->and(rowMessages($row, 'warning'))->toBe(['No URL can be made from the URL slug "!!!", so it is not used'])
        ->and($row->raw[3])->toBe('!!!');
});

test('a slug another product or an earlier product of the import uses warns with the suffixed slug', function () {
    productWithUrl('rochie-vara');

    $sheet = slugSheet($this->import, $this->productType->id, [
        2 => ['RV-1', 'Rochie vară', '199', 'Rochie vara'],
        3 => ['TR-1', 'Tricou', '49', 'tricou'],
        4 => ['TR-2', 'Tricou alb', '59', 'Tricou'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2), 'warning'))->toBe(['The URL "rochie-vara" is already used, so "rochie-vara-2" will be used instead'])
        ->and(rowMessages(stagedRow($sheet, 3), 'warning'))->toBe([])
        ->and(rowMessages(stagedRow($sheet, 4), 'warning'))->toBe(['The URL "tricou" is already used, so "tricou-2" will be used instead']);
});

test('a slug that differs from the url of an existing product warns that the product keeps its url', function () {
    productWithUrl('rochie-de-vara', 'HO-114-M');
    productWithUrl('geaca', 'GE-1');

    $sheet = slugSheet($this->import, $this->productType->id, [
        2 => ['HO-114-M', 'Rochie vară', '199', 'rochie-vara'],
        3 => ['GE-1', 'Geacă', '399', 'geaca'],
    ]);

    ImportBuilder::stage($this->import);

    expect(rowMessages(stagedRow($sheet, 2), 'warning'))->toBe(['The product keeps its URL "rochie-de-vara", so the URL "rochie-vara" is not used'])
        ->and(rowMessages(stagedRow($sheet, 3), 'warning'))->toBe([]);
});

/**
 * A sheet whose columns read numbers, dates, codes, images, and slugs, and a
 * row holding a value of each that cannot be read.
 */
function unreadableSheet(ProductImport $import, int $productTypeId): ProductImportSheet
{
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['Cod', 'Denumire', 'Pret', 'Sfarsit promotie', 'Comenzi', 'Imagini', 'URL'],
        ['TB-1', 'Tricou', '1,25', '24.12.2026', 'maybe', 'ftp://cdn.example/1.jpg', '!!!'],
    ]);

    return ImportBuilder::map($sheet, [
        0 => 'variant.sku',
        1 => ['product.name', 'ro'],
        2 => ['variant.price', null, ColumnFormat::number('.')],
        3 => ['variant.sale_ends', null, ColumnFormat::date('Y-m-d G:i:s')],
        4 => 'variant.backorders',
        5 => 'product.images',
        6 => ['product.slug', 'ro'],
    ], $productTypeId);
}

test('every value that cannot be read gives a message naming the field and the raw value, and the raw cells stay', function () {
    $sheet = unreadableSheet($this->import, $this->productType->id);

    ImportBuilder::stage($this->import);

    $row = stagedRow($sheet, 2);

    expect($row->status)->toBe(RowStatus::Error)
        ->and(rowMessages($row))->toBe([
            'The price "1,25" does not match the column format with a decimal dot and no thousands separator',
            'The sale end "24.12.2026" does not match the format "Y-m-d G:i:s"',
            'The backorders allowed "maybe" is not recognized',
        ])
        ->and(rowMessages($row, 'warning'))->toBe([
            'No URL can be made from the URL slug "!!!", so it is not used',
            'The image "ftp://cdn.example/1.jpg" is not an HTTP or HTTPS address',
        ])
        ->and(collect($row->messages)->pluck('field')->all())->toBe(['product.slug.ro', 'variant.price', 'variant.sale_ends', 'variant.backorders', 'product.images'])
        ->and($row->raw)->toBe([0 => 'TB-1', 1 => 'Tricou', 2 => '1,25', 3 => '24.12.2026', 4 => 'maybe', 5 => 'ftp://cdn.example/1.jpg', 6 => '!!!']);
});

test('a variant image that is not a web address is a warning on the variant image', function () {
    $sheet = ImportBuilder::sheet($this->import, 'Produse', [['Cod', 'Denumire', 'Pret', 'Imagine'], ['TB-1', 'Tricou', '10', 'ftp://cdn.example/2.jpg']]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'variant.image'], $this->productType->id);

    ImportBuilder::stage($this->import);

    expect(collect(stagedRow($sheet, 2)->messages)->where('code', 'image_not_web')->pluck('field')->all())->toBe(['variant.image']);
});

test('a row that names an option twice is validated with its first value, as it is written', function () {
    $sheet = ImportBuilder::sheet($this->import, 'Produse', [
        ['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Optiuni'],
        ['TB-1-S', 'TB-1', 'Tricou', '10', 'Size:S, Size:M'],
        ['TB-1-M', 'TB-1', 'Tricou', '10', 'Size:M'],
    ]);
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => 'group_key', 2 => ['product.name', 'ro'], 3 => 'variant.price', 4 => 'variant.options'], $this->productType->id, 1);

    ImportBuilder::stage($this->import);

    expect(stagedRow($sheet, 2)->effectiveRow()->namedOptions())->toBe(['name:size' => 'S'])
        ->and(rowMessages(stagedRow($sheet, 2)))->toBe([])
        ->and(rowMessages(stagedRow($sheet, 3)))->toBe([]);
});
