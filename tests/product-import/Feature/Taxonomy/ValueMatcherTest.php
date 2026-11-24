<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\MatchType;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    $this->productType = ProductType::factory()->create();
    $this->group = CollectionGroup::factory()->create();

    $femei = category('Femei', null, $this->group);
    $this->hanorac = category('Hanorac', $femei, $this->group);
    $this->rochii = category('Rochii', $femei, $this->group);
    category('Tricouri', $femei, $this->group);
    category('Barbati', null, $this->group);

    Brand::factory()->create(['name' => 'Nike']);

    $this->import = ImportBuilder::import(ImportStatus::Staging, [
        'options' => (new ImportOptions(collectionGroupId: $this->group->id))->toArray(),
    ]);
});

function category(string $name, ?Collection $parent, CollectionGroup $group): Collection
{
    $collection = Collection::factory()->make([
        'collection_group_id' => $group->id,
        'attribute_data' => collect(['name' => new Text($name)]),
    ]);

    $parent ? $parent->appendNode($collection) : $collection->save();

    return $collection->fresh();
}

/**
 * A sheet whose rows hold a category path, a brand, and a stock.
 *
 * @param  list<array{0: string, 1: string, 2?: string}>  $rows  [category, brand, stock]
 */
function taxonomySheet(ProductImport $import, string $name, array $rows, int $productTypeId): ProductImportSheet
{
    $data = [['Cod', 'Denumire', 'Categorie', 'Brand', 'Stoc']];

    foreach ($rows as $i => $row) {
        $data[] = ["{$name}-{$i}", "Produs {$i}", $row[0], $row[1], $row[2] ?? '1'];
    }

    $sheet = ImportBuilder::sheet($import, $name, $data);

    return ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'product.category', 3 => 'product.brand'], $productTypeId);
}

function storedValue(ProductImport $import, ValueType $type, string $key): ProductImportValue
{
    return $import->values()->where('type', $type)->where('normalized_key', $key)->firstOrFail();
}

test('nike and NIKE are one value and nike matches the brand nike', function () {
    taxonomySheet($this->import, 'Produse', [['Femei', 'Nike'], ['Femei', 'NIKE ']], $this->productType->id);

    ImportBuilder::stage($this->import);

    $brands = $this->import->values()->where('type', ValueType::Brand)->get();
    $nike = $brands->sole();

    expect($nike->source_value)->toBe('Nike')
        ->and($nike->product_count)->toBe(2)
        ->and($nike->match_type)->toBe(MatchType::Exact)
        ->and($nike->action)->toBe(ValueAction::UseExisting)
        ->and($nike->target_id)->toBe(Brand::where('name', 'Nike')->value('id'));
});

test('a new brand is set to be created', function () {
    taxonomySheet($this->import, 'Produse', [['Femei', 'Furnizor Textil']], $this->productType->id);

    ImportBuilder::stage($this->import);

    $brand = storedValue($this->import, ValueType::Brand, 'furnizor textil');

    expect($brand->match_type)->toBe(MatchType::None)
        ->and($brand->action)->toBe(ValueAction::Create)
        ->and($brand->create_name)->toBe('Furnizor Textil');
});

test('femei > hanorace is similar to femei > hanorac and has no default', function () {
    taxonomySheet($this->import, 'Produse', [['Femei > Hanorace', 'Nike']], $this->productType->id);

    ImportBuilder::stage($this->import);

    $value = storedValue($this->import, ValueType::Category, 'femei > hanorace');

    expect($value->match_type)->toBe(MatchType::Similar)
        ->and($value->matched_id)->toBe($this->hanorac->id)
        ->and($value->score)->toBeGreaterThanOrEqual(0.7)
        ->and($value->action)->toBeNull();
});

test('femei > rochii > vara creates vara under femei > rochii', function () {
    taxonomySheet($this->import, 'Produse', [['Femei > Rochii > Vară', 'Nike']], $this->productType->id);

    ImportBuilder::stage($this->import);

    $value = storedValue($this->import, ValueType::Category, 'femei > rochii > vara');

    expect($value->match_type)->toBe(MatchType::Partial)
        ->and($value->action)->toBe(ValueAction::Create)
        ->and($value->parent_id)->toBe($this->rochii->id)
        ->and($value->create_name)->toBe('Vară');
});

test('accesorii creates a root category', function () {
    taxonomySheet($this->import, 'Produse', [['Accesorii', 'Nike']], $this->productType->id);

    ImportBuilder::stage($this->import);

    $value = storedValue($this->import, ValueType::Category, 'accesorii');

    expect($value->match_type)->toBe(MatchType::None)
        ->and($value->action)->toBe(ValueAction::Create)
        ->and($value->parent_id)->toBeNull()
        ->and($value->create_name)->toBe('Accesorii');
});

test('femei > tricouri on two sheets is listed once with both sheets and 14 products', function () {
    taxonomySheet($this->import, 'Produse', array_fill(0, 9, ['Femei > Tricouri', 'Nike']), $this->productType->id);
    taxonomySheet($this->import, 'Outlet', [...array_fill(0, 5, ['Femei > Tricouri', 'Nike']), ['', 'Nike']], $this->productType->id);

    ImportBuilder::stage($this->import);

    $value = storedValue($this->import, ValueType::Category, 'femei > tricouri');

    expect($value->sheets)->toBe(['Produse', 'Outlet'])
        ->and($value->product_count)->toBe(14)
        ->and($value->match_type)->toBe(MatchType::Exact)
        ->and($this->import->fresh()->stats['review']['products_without_category'])->toBe(1);
});

test('each category of a list is reviewed as its own value', function () {
    taxonomySheet($this->import, 'Produse', [['Femei > Rochii, Femei > Noutăți', 'Nike'], ['Femei > Rochii', 'Nike']], $this->productType->id);

    ImportBuilder::stage($this->import);

    $rochii = storedValue($this->import, ValueType::Category, 'femei > rochii');
    $noutati = storedValue($this->import, ValueType::Category, 'femei > noutati');

    expect($this->import->values()->where('type', ValueType::Category)->count())->toBe(2)
        ->and($rochii->source_value)->toBe('Femei > Rochii')
        ->and($rochii->product_count)->toBe(2)
        ->and($rochii->match_type)->toBe(MatchType::Exact)
        ->and($rochii->target_id)->toBe($this->rochii->id)
        ->and($noutati->source_value)->toBe('Femei > Noutăți')
        ->and($noutati->product_count)->toBe(1)
        ->and($noutati->match_type)->toBe(MatchType::Partial);
});

test('a decision survives a stock column change and vanished values are removed', function () {
    $sheet = taxonomySheet($this->import, 'Produse', [['Femei > Hanorace', 'Nike'], ['Barbati', 'Puma']], $this->productType->id);

    ImportBuilder::stage($this->import);

    storedValue($this->import, ValueType::Category, 'femei > hanorace')->update([
        'action' => ValueAction::UseExisting,
        'target_id' => $this->hanorac->id,
    ]);

    // Map the stock column, and drop the Puma row by skipping its sheet rows' brand.
    $mapping = $sheet->fresh()->sheetMapping();
    $mapping->column(4)->target = 'variant.stock';
    $sheet->fresh()->forceFill(['mapping' => $mapping->toArray()])->save();
    $sheet->rows()->where('row_number', 3)->update(['raw' => json_encode([0 => 'Produse-1', 1 => 'Produs 1', 2 => 'Barbati', 4 => '1'])]);

    ImportBuilder::stage($this->import);

    $value = storedValue($this->import, ValueType::Category, 'femei > hanorace');

    expect($value->action)->toBe(ValueAction::UseExisting)
        ->and($value->target_id)->toBe($this->hanorac->id)
        ->and($this->import->values()->where('type', ValueType::Brand)->where('normalized_key', 'puma')->exists())->toBeFalse();
});

test('an option name uses an existing option through its name in another language, and properties use attributes or are skipped', function () {
    $material = Attribute::factory()->create(['handle' => 'material', 'type' => TranslatedText::class, 'name' => ['hu' => 'Anyag', 'ro' => 'Material']]);
    $this->productType->mappedAttributes()->attach([$material->id]);
    $meret = ProductOption::factory()->create(['handle' => 'meret', 'name' => ['hu' => 'Méret', 'ro' => 'Mărime']]);

    $sheet = ImportBuilder::sheet($this->import, 'wc-product-export', [
        1 => ['ID', 'Parent', 'SKU', 'Name', 'Price', 'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 2 name', 'Attribute 2 value(s)'],
        2 => ['57', '', '', 'Rochie vară', '', 'Material', 'Bumbac', 'Compoziție', 'Bumbac 100%'],
        3 => ['58', 'id:57', 'RV-S', '', '199,00', 'Mărime', 'S', '', ''],
        4 => ['59', 'id:57', 'RV-M', '', '199,00', 'Mărime', 'M', '', ''],
    ]);

    ImportBuilder::map($sheet, [
        0 => 'source.id', 1 => 'source.parent', 2 => 'variant.sku', 3 => 'product.name', 4 => 'variant.price',
        5 => ['variant.option_name', 1], 6 => ['variant.option_value', 1], 7 => ['variant.option_name', 2], 8 => ['variant.option_value', 2],
    ], $this->productType->id, strategy: GroupingStrategy::ByParent);

    ImportBuilder::stage($this->import);

    $size = storedValue($this->import, ValueType::Option, 'marime');
    $materialValue = storedValue($this->import, ValueType::Property, 'material');
    $composition = storedValue($this->import, ValueType::Property, 'compozitie');

    expect($size->match_type)->toBe(MatchType::Exact)
        ->and($size->action)->toBe(ValueAction::UseExisting)
        ->and($size->target_id)->toBe($meret->id)
        ->and(storedValue($this->import, ValueType::OptionValue, 's')->option_key)->toBe('name:marime')
        ->and($materialValue->action)->toBe(ValueAction::UseExisting)
        ->and($materialValue->target_id)->toBe($material->id)
        ->and($materialValue->option_key)->toBe('type:'.$this->productType->id)
        ->and($composition->action)->toBe(ValueAction::Skip)
        ->and($composition->match_type)->toBe(MatchType::None);
});
