<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\Text;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
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
