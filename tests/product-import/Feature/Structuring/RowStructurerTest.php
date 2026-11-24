<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Structuring\RowStructurer;
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
 * A sheet of a shop export grouped by parent: ID, parent, SKU, name, price,
 * weight, stock, and an option pair.
 *
 * @param  array<int, list<?string>>  $rows  Data rows keyed by row number (header is row 1).
 */
function parentSheet(ProductImport $import, int $productTypeId, array $rows, string $name = 'export'): ProductImportSheet
{
    $sheet = ImportBuilder::sheet($import, $name, [1 => ['ID', 'Parent', 'SKU', 'Name', 'Price', 'Weight', 'Stock', 'Attribute 1 name', 'Attribute 1 value(s)', 'Price impact']] + $rows);

    return ImportBuilder::map($sheet, [
        0 => 'source.id',
        1 => 'source.parent',
        2 => 'variant.sku',
        3 => ['product.name', 'ro'],
        4 => 'variant.price',
        5 => 'variant.weight',
        6 => 'variant.stock',
        7 => ['variant.option_name', 1],
        8 => ['variant.option_value', 1],
        9 => 'variant.price_impact',
    ], $productTypeId, strategy: GroupingStrategy::ByParent, weightUnit: 'kg');
}

function structuredRow(ProductImportSheet $sheet, int $rowNumber): ProductImportRow
{
    return ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', $rowNumber)->firstOrFail();
}

test('variations far from their parent join it, and a combination on another sheet joins its product', function () {
    $export = parentSheet($this->import, $this->productType->id, [
        3 => ['57', '', '', 'Rochie vară', '', '', '', 'Size', 'S, M', ''],
        4 => ['58', 'id:57', 'RV-S', '', '199,00', '', '', 'Size', 'S', ''],
        5 => ['60', '', 'TB-1', 'Tricou', '49,90', '', '', '', '', ''],
        90 => ['59', 'id:57', 'RV-M', '', '199,00', '', '', 'Size', 'M', ''],
    ]);
    $products = parentSheet($this->import, $this->productType->id, [
        2 => ['12', '', '', 'Cămașă', '100,00', '0,3', '', '', '', ''],
    ], 'products_import.csv');
    $combinations = parentSheet($this->import, $this->productType->id, [
        2 => ['', '12', 'CAM-S', '', '', '', '', 'Size', 'S', '0,00'],
        3 => ['', '12', 'CAM-M', '', '', '', '', 'Size', 'M', '10,00'],
    ], 'combinations_import.csv');

    ImportBuilder::stage($this->import);

    $parent = structuredRow($export, 3);

    expect($parent->role)->toBe(RowRole::Product)
        ->and(structuredRow($export, 4)->role)->toBe(RowRole::Variant)
        ->and(structuredRow($export, 4)->group_key)->toBe($parent->group_key)
        ->and(structuredRow($export, 90)->group_key)->toBe($parent->group_key)
        ->and(structuredRow($export, 5)->role)->toBe(RowRole::ProductAndVariant)
        ->and(structuredRow($export, 5)->group_key)->not->toBe($parent->group_key)
        ->and(structuredRow($products, 2)->role)->toBe(RowRole::Product)
        ->and(structuredRow($combinations, 2)->group_key)->toBe(structuredRow($products, 2)->group_key)
        ->and(structuredRow($combinations, 3)->group_key)->toBe(structuredRow($products, 2)->group_key)
        ->and(structuredRow($combinations, 2)->parent_ref)->toBe('12');
});

test('a parent sku of an existing variant joins its product, and a reference to nothing is an error', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product, 'product')->create(['sku' => 'HO-114-M']);

    $sheet = parentSheet($this->import, $this->productType->id, [
        2 => ['', 'HO-114-M', 'HO-114-XL', '', '219,00', '', '3', 'Size', 'XL', ''],
        3 => ['', '99', 'CAM-99-S', '', '100,00', '', '3', 'Size', 'S', ''],
    ]);

    ImportBuilder::stage($this->import);

    $joined = structuredRow($sheet, 2);
    $orphan = structuredRow($sheet, 3);

    expect($joined->group_key)->toBe('product:'.$product->id)
        ->and($joined->matched_product_id)->toBe($product->id)
        ->and($joined->messages)->toBe([])
        ->and(collect($orphan->messages)->pluck('message')->all())->toContain('The parent "99" was not found');
});

test('a variation takes the weight of its product row, and an impact gives the base price plus the impact', function () {
    $sheet = parentSheet($this->import, $this->productType->id, [
        2 => ['12', '', '', 'Cămașă', '100,00', '0,5', '', '', '', ''],
        3 => ['', '12', 'CAM-S', '', '', '', '2', 'Size', 'S', '15,50'],
        4 => ['', '12', 'CAM-M', '', '120,00', '0,7', '2', 'Size', 'M', ''],
    ]);

    ImportBuilder::stage($this->import);

    $inherited = structuredRow($sheet, 3);
    $own = structuredRow($sheet, 4)->effectiveRow();

    expect($inherited->effectiveRow()->get('variant.weight'))->toBe(0.5)
        ->and($inherited->effectiveRow()->get('variant.price'))->toBe(11550)
        ->and($inherited->normalized['variant.price'])->toBeNull()
        ->and($own->get('variant.weight'))->toBe(0.7)
        ->and($own->get('variant.price'))->toBe(12000);
});

test('a pair value without a name takes the name of the same pair from the product row', function () {
    $sheet = parentSheet($this->import, $this->productType->id, [
        2 => ['12', '', '', 'Tricou', '49,90', '', '', 'Size', 'S, M', ''],
        3 => ['', '12', 'TB-S', '', '49,90', '', '2', '', 'S', ''],
        4 => ['', '12', 'TB-M', '', '49,90', '', '2', '', 'M', ''],
    ]);

    ImportBuilder::stage($this->import);

    expect(structuredRow($sheet, 3)->effectiveRow()->get(RowStructurer::NAMED_OPTIONS))->toBe([['name' => 'Size', 'value' => 'S']])
        ->and(structuredRow($sheet, 4)->effectiveRow()->get(RowStructurer::NAMED_OPTIONS))->toBe([['name' => 'Size', 'value' => 'M']]);
});

test('on a product row, pairs that are options of the group are skipped and the others are properties', function () {
    $sheet = ImportBuilder::sheet($this->import, 'export', [
        1 => ['ID', 'Parent', 'SKU', 'Name', 'Price', 'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 2 name', 'Attribute 2 value(s)'],
        2 => ['57', '', '', 'Rochie vară', '', 'Material', 'Bumbac', 'Size', 'S, M, L'],
        3 => ['', 'id:57', 'RV-S', '', '199,00', 'Size', 'S', '', ''],
        4 => ['', 'id:57', 'RV-M', '', '199,00', 'Size', 'M', '', ''],
    ]);
    ImportBuilder::map($sheet, [
        0 => 'source.id', 1 => 'source.parent', 2 => 'variant.sku', 3 => ['product.name', 'ro'], 4 => 'variant.price',
        5 => ['variant.option_name', 1], 6 => ['variant.option_value', 1], 7 => ['variant.option_name', 2], 8 => ['variant.option_value', 2],
    ], $this->productType->id, strategy: GroupingStrategy::ByParent);

    ImportBuilder::stage($this->import);

    $parent = structuredRow($sheet, 2)->effectiveRow();

    expect($parent->get(RowStructurer::NAMED_PROPERTIES))->toBe([['name' => 'Material', 'value' => 'Bumbac']])
        ->and($parent->get(RowStructurer::NAMED_OPTIONS))->toBeNull()
        ->and(structuredRow($sheet, 3)->effectiveRow()->get(RowStructurer::NAMED_OPTIONS))->toBe([['name' => 'Size', 'value' => 'S']])
        ->and(structuredRow($sheet, 2)->status->value)->toBe('valid');
});

/**
 * A sheet grouped by parent with a row type column whose types have the
 * given meanings, and an option pair.
 *
 * @param  array<int, list<?string>>  $rows  Data rows keyed by row number (header is row 1).
 * @param  array<string, string>  $meanings  Type => meaning.
 */
function typedSheet(ProductImport $import, int $productTypeId, array $rows, array $meanings = [
    'simple' => 'single', 'variable' => 'parent', 'variation' => 'variant', 'grouped' => 'skip', 'virtual' => 'not_shippable',
]): ProductImportSheet
{
    $sheet = ImportBuilder::sheet($import, 'export', [1 => ['ID', 'Type', 'Parent', 'SKU', 'Name', 'Price', 'Option 1 name', 'Option 1 values']] + $rows);

    return ImportBuilder::map($sheet, [
        0 => 'source.id',
        1 => ['source.type', null, ColumnFormat::meanings($meanings)],
        2 => 'source.parent',
        3 => 'variant.sku',
        4 => ['product.name', 'ro'],
        5 => 'variant.price',
        6 => ['variant.option_name', 1],
        7 => ['variant.option_value', 1],
    ], $productTypeId, strategy: GroupingStrategy::ByParent);
}

test('a parent type and its variant types give one product with two variants', function () {
    $sheet = typedSheet($this->import, $this->productType->id, [
        2 => ['57', 'variable', '', '', 'Rochie vară', '', 'Size', 'S, M'],
        3 => ['58', 'variation', 'id:57', 'RV-S', '', '199,00', 'Size', 'S'],
        4 => ['59', 'variation', 'id:57', 'RV-M', '', '199,00', 'Size', 'M'],
    ]);

    ImportBuilder::stage($this->import);

    $parent = structuredRow($sheet, 2);

    expect($parent->role)->toBe(RowRole::Product)
        ->and($parent->status)->toBe(RowStatus::Valid)
        ->and(structuredRow($sheet, 3)->role)->toBe(RowRole::Variant)
        ->and(structuredRow($sheet, 4)->role)->toBe(RowRole::Variant)
        ->and(ProductImportRow::includedIn($this->import)->where('group_key', $parent->group_key)->count())->toBe(3)
        ->and(ProductImportRow::includedIn($this->import)->pluck('group_key')->unique())->toHaveCount(1);
});

test('a type that is not imported skips its row with a message naming it, and its members are imported', function () {
    $sheet = typedSheet($this->import, $this->productType->id, [
        2 => ['90', 'grouped', '', 'SET-90', 'Set vară', '', '', ''],
        3 => ['10', 'simple', '', 'CARD-10', 'Card cadou', '100,00', '', ''],
        4 => ['60', 'simple', '', 'GP-60', 'Geacă puf', '1256,59', '', ''],
    ]);

    ImportBuilder::stage($this->import);

    $grouped = structuredRow($sheet, 2);

    expect($grouped->status)->toBe(RowStatus::Skipped)
        ->and($grouped->skip_reason)->toBe(RowSkipReason::RowType)
        ->and(collect($grouped->messages)->pluck('message')->all())->toBe(['Rows of the type “grouped” are not imported.'])
        ->and(structuredRow($sheet, 3)->status)->toBe(RowStatus::Valid)
        ->and(structuredRow($sheet, 4)->status)->toBe(RowStatus::Valid)
        ->and($this->import->values()->where('source_value', 'Set vară')->exists())->toBeFalse();
});

test('a single product whose type is also virtual has one variant that is not shippable', function () {
    $sheet = typedSheet($this->import, $this->productType->id, [
        2 => ['10', 'simple, virtual', '', 'CARD-10', 'Card cadou', '100,00', '', ''],
        3 => ['60', 'simple', '', 'GP-60', 'Geacă puf', '1256,59', '', ''],
    ]);

    ImportBuilder::stage($this->import);

    $card = structuredRow($sheet, 2);

    expect($card->role)->toBe(RowRole::ProductAndVariant)
        ->and($card->effectiveRow()->get('variant.shippable'))->toBeFalse()
        ->and($card->normalized)->not->toHaveKey('variant.shippable')
        ->and(structuredRow($sheet, 3)->effectiveRow()->get('variant.shippable'))->toBeNull()
        ->and(ProductImportRow::includedIn($this->import)->where('group_key', $card->group_key)->count())->toBe(1);
});

test('the pairs of a single product are properties', function () {
    $sheet = typedSheet($this->import, $this->productType->id, [
        2 => ['60', 'simple', '', 'GP-60', 'Geacă puf', '1256,59', 'Color', 'Roșu'],
    ]);

    ImportBuilder::stage($this->import);

    $jacket = structuredRow($sheet, 2)->effectiveRow();

    expect($jacket->get(RowStructurer::NAMED_PROPERTIES))->toBe([['name' => 'Color', 'value' => 'Roșu']])
        ->and($jacket->get(RowStructurer::NAMED_OPTIONS))->toBeNull()
        ->and($this->import->values()->where('type', 'property')->pluck('source_value')->all())->toBe(['Color'])
        ->and($this->import->values()->where('type', 'option')->exists())->toBeFalse();
});

test('a type without a meaning follows the general rule', function () {
    $sheet = typedSheet($this->import, $this->productType->id, [
        2 => ['70', 'bundle', '', '', 'Pachet', '', 'Size', 'S, M'],
        3 => ['71', 'bundle', 'id:70', 'PK-S', '', '99,00', 'Size', 'S'],
        4 => ['72', 'bundle', '', 'PK-X', 'Pachet mic', '49,00', '', ''],
    ]);

    ImportBuilder::stage($this->import);

    expect(structuredRow($sheet, 2)->role)->toBe(RowRole::Product)
        ->and(structuredRow($sheet, 3)->role)->toBe(RowRole::Variant)
        ->and(structuredRow($sheet, 4)->role)->toBe(RowRole::ProductAndVariant)
        ->and(structuredRow($sheet, 2)->skip_reason)->toBeNull();
});
