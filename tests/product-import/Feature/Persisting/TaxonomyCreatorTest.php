<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Facades\ModelManifest;
use Lunar\FieldTypes\Text;
use Lunar\Models\Brand;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Contracts\Collection as CollectionContract;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Language;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\MatchType;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Jobs\CommitTaxonomy;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\Support\Models\Collection as StoreCollection;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    $this->group = CollectionGroup::factory()->create();

    $femei = Collection::factory()->create(['collection_group_id' => $this->group->id, 'attribute_data' => collect(['name' => new Text('Femei')])]);
    $this->rochii = Collection::factory()->make(['collection_group_id' => $this->group->id, 'attribute_data' => collect(['name' => new Text('Rochii')])]);
    $femei->appendNode($this->rochii);

    $this->nike = Brand::factory()->create(['name' => 'Nike']);

    $this->import = ImportBuilder::import(ImportStatus::Importing, [
        'options' => (new ImportOptions(collectionGroupId: $this->group->id))->toArray(),
    ]);

    $sheet = ImportBuilder::sheet($this->import, 'Produse', [['Cod', 'Marime'], ['A-1', 'S']]);
    $sheet->forceFill(['mapping' => (new SheetMapping(columns: [
        new ColumnMapping(0, 'Cod', 'variant.sku'),
        new ColumnMapping(1, 'Marime', 'variant.option', optionName: 'Marime'),
    ]))->toArray()])->save();
});

function decidedImportValue(ProductImport $import, ValueType $type, string $source, array $attributes = []): ProductImportValue
{
    return ProductImportValue::factory()->for($import, 'import')->create([
        'type' => $type,
        'source_value' => $source,
        'normalized_key' => mb_strtolower($source),
        'match_type' => MatchType::None,
        'action' => ValueAction::Create,
        'create_name' => $source,
        ...$attributes,
    ]);
}

function runCommitTaxonomy(ProductImport $import): void
{
    app()->call([new CommitTaxonomy($import->id), 'handle']);
}

test('new categories, brands, options, and option values are created once each', function () {
    $accesorii = decidedImportValue($this->import, ValueType::Category, 'Accesorii');
    $brand = decidedImportValue($this->import, ValueType::Brand, 'Furnizor Textil');
    $size = decidedImportValue($this->import, ValueType::OptionValue, 'S', ['option_key' => 'new:marime']);

    runCommitTaxonomy($this->import);

    $collection = Collection::query()->find($accesorii->fresh()->target_id);
    $option = ProductOption::query()->where('handle', 'marime')->sole();

    expect($collection->translateAttribute('name'))->toBe('Accesorii')
        ->and($collection->parent_id)->toBeNull()
        ->and($collection->collection_group_id)->toBe($this->group->id)
        ->and(Brand::query()->find($brand->fresh()->target_id)->name)->toBe('Furnizor Textil')
        ->and($option->shared)->toBeTrue()
        ->and($this->import->fresh()->resolved_options)->toBe(['new:marime' => $option->id])
        ->and(ProductOptionValue::query()->find($size->fresh()->target_id)->product_option_id)->toBe($option->id)
        ->and($this->import->fresh()->stats['created']['categories'][0]['name'])->toBe('Accesorii');
});

test('vara is created as a child of femei > rochii', function () {
    $vara = decidedImportValue($this->import, ValueType::Category, 'Femei > Rochii > Vară', [
        'match_type' => MatchType::Partial,
        'create_name' => 'Vară',
        'parent_id' => $this->rochii->id,
    ]);

    runCommitTaxonomy($this->import);

    $created = Collection::query()->find($vara->fresh()->target_id);

    expect($created->translateAttribute('name'))->toBe('Vară')
        ->and($created->parent_id)->toBe($this->rochii->id);
});

test('a new category path is created nested with the store\'s own collection class', function () {
    // The store's class fires each model event twice (as itself and as
    // Lunar's class), so the tree's saving listener runs twice per save.
    ModelManifest::replace(CollectionContract::class, StoreCollection::class);

    $chinos = decidedImportValue($this->import, ValueType::Category, 'Barbati > Pantaloni > Chinos');
    $vara = decidedImportValue($this->import, ValueType::Category, 'Vară', ['parent_id' => $this->rochii->id]);

    runCommitTaxonomy($this->import);

    $chinosCollection = Collection::query()->find($chinos->fresh()->target_id);
    $pantaloni = Collection::query()->find($chinosCollection->parent_id);
    $barbati = Collection::query()->find($pantaloni?->parent_id);
    $varaCollection = Collection::query()->find($vara->fresh()->target_id);

    expect($chinosCollection)->toBeInstanceOf(StoreCollection::class)
        ->and($pantaloni?->translateAttribute('name'))->toBe('Pantaloni')
        ->and($barbati?->translateAttribute('name'))->toBe('Barbati')
        ->and($barbati?->parent_id)->toBeNull()
        ->and($varaCollection->parent_id)->toBe($this->rochii->id)
        ->and(Collection::query()->where('collection_group_id', $this->group->id)->countErrors())
        ->toMatchArray(['oddness' => 0, 'duplicates' => 0, 'wrong_parent' => 0, 'missing_parent' => 0])
        ->and($chinosCollection->isDescendantOf($barbati))->toBeTrue();
});

test('two values set to create furnizor textil create one brand', function () {
    $first = decidedImportValue($this->import, ValueType::Brand, 'Furnizor Textil SRL', ['create_name' => 'Furnizor Textil']);
    $second = decidedImportValue($this->import, ValueType::Brand, 'Furnizor Textil S.R.L.', ['create_name' => 'furnizor textil']);

    runCommitTaxonomy($this->import);

    expect(Brand::query()->where('name', 'Furnizor Textil')->count())->toBe(1)
        ->and($first->fresh()->target_id)->toBe($second->fresh()->target_id);
});

test('nike inc set to the existing nike creates no brand', function () {
    decidedImportValue($this->import, ValueType::Brand, 'Nike Inc', [
        'action' => ValueAction::UseExisting,
        'target_id' => $this->nike->id,
        'create_name' => null,
    ]);

    $brands = Brand::count();

    runCommitTaxonomy($this->import);

    expect(Brand::count())->toBe($brands);
});

test('an exception rolls everything back and fails the import', function () {
    decidedImportValue($this->import, ValueType::Brand, 'Furnizor Textil');
    decidedImportValue($this->import, ValueType::Category, 'Lipsa > Copil', ['create_name' => 'Copil', 'parent_id' => 999999]);

    $brands = Brand::count();
    $options = ProductOption::count();
    $job = new CommitTaxonomy($this->import->id);

    try {
        app()->call([$job, 'handle']);
        $this->fail('The taxonomy was created.');
    } catch (RuntimeException $exception) {
        $job->failed($exception);
    }

    expect(Brand::count())->toBe($brands)
        ->and(ProductOption::count())->toBe($options)
        ->and($this->import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($this->import->fresh()->failure_message)->toBe('Creating the categories, brands, and options failed, so no product was written.');
});

test('a discount without a start becomes one discount for every customer that starts now and ends at the end of its day', function () {
    $this->travelTo(now()->setDate(2026, 11, 24)->setTime(10, 30));

    $web = Channel::factory()->create(['default' => true]);
    $app = Channel::factory()->create(['default' => false]);
    $retail = CustomerGroup::factory()->create(['default' => true]);
    $wholesale = CustomerGroup::factory()->create(['default' => false]);

    $first = ProductImportValue::factory()->for($this->import, 'import')->discount()->create(['create_name' => 'Outlet iarnă']);
    $second = ProductImportValue::factory()->for($this->import, 'import')->discount()->create([
        'normalized_key' => 'percent:30||2026-12-31 23:59:59 (other sheet spelling)',
        'create_name' => 'Outlet',
    ]);

    runCommitTaxonomy($this->import);

    $discount = Discount::query()->sole();

    expect($first->fresh()->target_id)->toBe($discount->id)
        ->and($second->fresh()->target_id)->toBe($discount->id)
        ->and($discount->name)->toBe('Outlet iarnă')
        ->and($discount->handle)->toBe('outlet-iarna')
        ->and($discount->type)->toBe(AdvancedAmountOff::class)
        ->and($discount->coupon)->toBeNull()
        ->and($discount->data)->toBe(['fixed_value' => false, 'free_shipping' => false, 'percentage' => 30])
        ->and($discount->starts_at->format('Y-m-d H:i'))->toBe('2026-11-24 10:30')
        ->and($discount->ends_at->format('Y-m-d H:i:s'))->toBe('2026-12-31 23:59:59')
        ->and($discount->channels()->wherePivot('enabled', true)->pluck('lunar_channels.id')->sort()->values()->all())->toBe([$web->id, $app->id])
        ->and($discount->customerGroups()->wherePivot('enabled', true)->wherePivot('visible', true)->pluck('lunar_customer_groups.id')->sort()->values()->all())->toBe([$retail->id, $wholesale->id])
        ->and($this->import->fresh()->stats['created']['discounts'])->toBe([['id' => $discount->id, 'name' => 'Outlet iarnă']]);
});

test('an amount discount is set for the default currency, and a taken handle gets a number', function () {
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    Discount::factory()->create(['handle' => 'reducere']);

    $value = ProductImportValue::factory()->for($this->import, 'import')->discount('amount', 2000, '2026-12-01 00:00:00', '2026-12-31 23:59:59')->create(['create_name' => 'Reducere']);

    runCommitTaxonomy($this->import);

    $discount = Discount::query()->find($value->fresh()->target_id);

    expect($discount->handle)->toBe('reducere-2')
        ->and($discount->data)->toBe(['fixed_value' => true, 'free_shipping' => false, 'fixed_values' => ['RON' => 2000]])
        ->and($discount->starts_at->format('Y-m-d H:i:s'))->toBe('2026-12-01 00:00:00');
});
