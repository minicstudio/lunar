<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Actions\ContinueToPreview;
use Lunar\ProductImport\Actions\DecideValue;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\MatchType;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Enums\ValueAction;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\ProductImport\Persisting\TaxonomyCreator;
use Lunar\ProductImport\Taxonomy\PlannedRecords;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    $productType = ProductType::factory()->create();
    $this->group = CollectionGroup::factory()->create();

    $barbati = Collection::factory()->create(['collection_group_id' => $this->group->id, 'attribute_data' => collect(['name' => new Text('Barbati')])]);
    $this->geaca = Collection::factory()->make(['collection_group_id' => $this->group->id, 'attribute_data' => collect(['name' => new Text('Geacă')])]);
    $barbati->appendNode($this->geaca);

    $this->import = ImportBuilder::import(ImportStatus::Staging, [
        'options' => (new ImportOptions(collectionGroupId: $this->group->id))->toArray(),
    ]);

    $sheet = ImportBuilder::sheet($this->import, 'Produse', [
        ['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Categorie', 'Brand', 'Marime'],
        ['GC-1-M', 'GC-1', 'Geacă iarnă', '399', 'Barbati > Geci', 'Furnizor Textil SRL', 'M'],
        ['GC-1-XXS', 'GC-1', 'Geacă iarnă', '399', 'Barbati > Geci', 'Furnizor Textil SRL', 'XXS'],
        ['RO-1-XXS', 'RO-1', 'Rochie', '199', 'Accesorii', 'Furnizor Textil S.R.L.', 'XXS'],
        ['RO-1-S', 'RO-1', 'Rochie', '199', 'Accesorii', 'Furnizor Textil S.R.L.', 'S'],
        ['GV-1-M', 'GV-1', 'Geacă vânt', '299', 'Barbati > Geaci', 'Furnizor Textil SRL', 'M'],
    ]);

    ImportBuilder::map($sheet, [
        0 => 'variant.sku', 1 => 'group_key', 2 => ['product.name', 'ro'], 3 => 'variant.price',
        4 => 'product.category', 5 => 'product.brand', 6 => 'variant.option',
    ], $productType->id, 1);

    ImportBuilder::stage($this->import);
    $this->import->update(['status' => ImportStatus::ReviewingValues]);
});

function decidedValue(string $type, string $key): ProductImportValue
{
    return ProductImportValue::query()->where('type', $type)->where('normalized_key', $key)->firstOrFail();
}

test('barbati > geci set to use barbati > geaca', function () {
    $geci = decidedValue('category', 'barbati > geci');

    // "Geci" is 60% like "Geacă": below the similar share, so it defaults to a new level.
    expect($geci->match_type)->toBe(MatchType::Partial)
        ->and($geci->action)->toBe(ValueAction::Create);

    app(DecideValue::class)->useExisting($geci, $this->geaca->id);

    expect($geci->fresh()->action)->toBe(ValueAction::UseExisting)
        ->and($geci->fresh()->target_id)->toBe($this->geaca->id);
});

test('skipping xxs skips its 2 variants', function () {
    $xxs = decidedValue('option_value', 'xxs');

    expect($xxs->variant_count)->toBe(2);

    app(DecideValue::class)->skip($xxs);

    $skipped = ProductImportRow::query()->where('status', RowStatus::Skipped)->get();

    expect($skipped)->toHaveCount(2)
        ->and($skipped->pluck('skip_reason')->unique()->all())->toBe([RowSkipReason::OptionValue])
        ->and($skipped->pluck('match_value')->sort()->values()->all())->toBe(['GC-1-XXS', 'RO-1-XXS']);
});

test('accesorii changed to skip leaves those products without a category', function () {
    $accesorii = decidedValue('category', 'accesorii');

    expect($accesorii->action)->toBe(ValueAction::Create);

    app(DecideValue::class)->skip($accesorii);

    $rows = ProductImportRow::query()->whereIn('match_value', ['RO-1-XXS', 'RO-1-S'])->get();

    foreach ($rows as $row) {
        expect(collect($row->messages)->pluck('message')->all())->toContain('The product has no category');
    }

    expect(collect(ProductImportRow::query()->where('match_value', 'GC-1-M')->first()->messages)->pluck('code')->all())
        ->not->toContain('no_category');
});

test('continuing with one open decision is refused', function () {
    expect(fn () => app(ContinueToPreview::class)->handle($this->import->fresh()))
        ->toThrow(ImportActionRefused::class, 'There is 1 open decision. Decide it before continuing.');

    $geaci = decidedValue('category', 'barbati > geaci');

    expect($geaci->match_type)->toBe(MatchType::Similar)
        ->and($geaci->matched_id)->toBe($this->geaca->id);

    app(DecideValue::class)->useExisting($geaci, $this->geaca->id);
    app(ContinueToPreview::class)->handle($this->import->fresh());

    expect($this->import->fresh()->status)->toBe(ImportStatus::PreviewReady);
});

test('two values set to create furnizor textil resolve to one planned record', function () {
    app(DecideValue::class)->create(decidedValue('brand', 'furnizor textil srl'), 'Furnizor Textil');
    app(DecideValue::class)->create(decidedValue('brand', 'furnizor textil s.r.l.'), 'furnizor textil');

    $brands = array_values(array_filter(
        app(PlannedRecords::class)->for($this->import),
        fn ($record) => $record->type === ValueType::Brand,
    ));

    expect($brands)->toHaveCount(1)
        ->and($brands[0]->name)->toBe('Furnizor Textil')
        ->and($brands[0]->valueIds)->toHaveCount(2);
});

test('a category parent outside the collection group is refused', function () {
    $other = Collection::factory()->create(['attribute_data' => collect(['name' => new Text('Alt grup')])]);

    expect(fn () => app(DecideValue::class)->create(decidedValue('category', 'accesorii'), 'Accesorii', $other->id))
        ->toThrow(ImportActionRefused::class, 'The parent category must belong to the chosen collection group.');
});

test('decisions are refused while staging', function () {
    $this->import->update(['status' => ImportStatus::Staging]);

    expect(fn () => app(DecideValue::class)->skip(decidedValue('category', 'accesorii')))
        ->toThrow(ImportActionRefused::class, 'The import is being processed. Wait until it has finished.');
});

/**
 * A new import with a sheet whose variable products name their options in
 * cells, like a shop export, staged.
 *
 * @param  array<int, list<?string>>  $rows
 */
function cellOptionsImport(array $rows, ?callable $before = null): ProductImport
{
    $productType = ProductType::factory()->create();
    $import = ImportBuilder::import(ImportStatus::Staging);

    $before && $before($import, $productType->id);

    $sheet = ImportBuilder::sheet($import, 'wc-product-export', [1 => ['ID', 'Parent', 'SKU', 'Name', 'Price', 'Attribute 1 name', 'Attribute 1 value(s)', 'Attribute 2 name', 'Attribute 2 value(s)']] + $rows);

    ImportBuilder::map($sheet, [
        0 => 'source.id', 1 => 'source.parent', 2 => 'variant.sku', 3 => 'product.name', 4 => 'variant.price',
        5 => ['variant.option_name', 1], 6 => ['variant.option_value', 1], 7 => ['variant.option_name', 2], 8 => ['variant.option_value', 2],
    ], $productType->id, strategy: GroupingStrategy::ByParent);

    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::ReviewingValues]);

    return $import->fresh();
}

function cellValue(ProductImport $import, ValueType $type, string $source): ProductImportValue
{
    return $import->values()->where('type', $type)->where('source_value', $source)->sole();
}

test('setting culoare to use szin matches its values against szin', function () {
    $szin = ProductOption::factory()->create(['handle' => 'szin', 'name' => ['hu' => 'Szín', 'ro' => 'Culoare']]);
    $piros = ProductOptionValue::factory()->for($szin, 'option')->create(['name' => ['hu' => 'Piros', 'ro' => 'Rosu']]);

    $import = cellOptionsImport([
        2 => ['57', '', '', 'Tricou', '', 'Culoarea', 'Rosu, Verde', '', ''],
        3 => ['58', 'id:57', 'TB-R', '', '49,00', 'Culoarea', 'Rosu', '', ''],
        4 => ['59', 'id:57', 'TB-V', '', '49,00', 'Culoarea', 'Verde', '', ''],
    ]);

    expect(cellValue($import, ValueType::Option, 'Culoarea')->match_type)->toBe(MatchType::Similar)
        ->and(cellValue($import, ValueType::OptionValue, 'Rosu')->action)->toBe(ValueAction::Create);

    app(DecideValue::class)->useExisting(cellValue($import, ValueType::Option, 'Culoarea'), $szin->id);

    expect(cellValue($import, ValueType::OptionValue, 'Rosu')->match_type)->toBe(MatchType::Exact)
        ->and(cellValue($import, ValueType::OptionValue, 'Rosu')->action)->toBe(ValueAction::UseExisting)
        ->and(cellValue($import, ValueType::OptionValue, 'Rosu')->target_id)->toBe($piros->id)
        ->and(cellValue($import, ValueType::OptionValue, 'Verde')->action)->toBe(ValueAction::Create);
});

test('skipping an option name imports its variants without it', function () {
    TaxClass::factory()->create(['default' => true]);
    $meret = ProductOption::factory()->create(['handle' => 'meret', 'name' => ['hu' => 'Méret', 'ro' => 'Mărime']]);

    $import = cellOptionsImport([
        2 => ['57', '', '', 'Tricou', '', 'Mărime', 'S, M', 'Croială', 'Slim'],
        3 => ['58', 'id:57', 'TB-S', '', '49,00', 'Mărime', 'S', 'Croială', 'Slim'],
        4 => ['59', 'id:57', 'TB-M', '', '49,00', 'Mărime', 'M', 'Croială', 'Slim'],
    ]);

    app(DecideValue::class)->skip(cellValue($import, ValueType::Option, 'Croială'));

    expect(ProductImportRow::includedIn($import)->pluck('status')->unique()->all())->toBe([RowStatus::Valid])
        ->and(cellValue($import, ValueType::Option, 'Croială')->action)->toBe(ValueAction::Skip);

    ImportBuilder::commit($import);

    $product = Product::query()->sole();

    expect($product->productOptions()->pluck('handle')->all())->toBe(['meret'])
        ->and(ProductOption::query()->count())->toBe(1)
        ->and($product->variants()->get()->map(fn ($variant) => $variant->values->map(fn ($value) => $value->translate('name', 'ro'))->all())->all())->toBe([['S'], ['M']]);
});

test('an option named in cells and a mapped new option with the same name create one option', function () {
    $import = cellOptionsImport([
        2 => ['57', '', '', 'Rochie', '', 'Croiala', 'Slim, Lejer', '', ''],
        3 => ['58', 'id:57', 'RO-S', '', '199,00', 'Croiala', 'Slim', '', ''],
        4 => ['59', 'id:57', 'RO-L', '', '199,00', 'Croiala', 'Lejer', '', ''],
    ], function (ProductImport $import, int $productTypeId): void {
        $sheet = ImportBuilder::sheet($import, 'Produse', [
            ['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Croiala'],
            ['TB-1-S', 'TB-1', 'Tricou', '49,90', 'Slim'],
            ['TB-1-M', 'TB-1', 'Tricou', '49,90', 'Drept'],
        ]);
        ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => 'group_key', 2 => 'product.name', 3 => 'variant.price', 4 => ['variant.option', 'Croiala']], $productTypeId, 1);
    });

    expect(cellValue($import, ValueType::Option, 'Croiala')->action)->toBe(ValueAction::Create);

    $created = app(TaxonomyCreator::class)->create($import);
    $option = ProductOption::query()->where('handle', 'croiala')->sole();

    expect($created['options'])->toHaveCount(1)
        ->and(ProductOption::query()->count())->toBe(1)
        ->and($import->fresh()->resolved_options)->toMatchArray(['new:croiala' => $option->id, 'name:croiala' => $option->id])
        ->and(ProductOptionValue::query()->where('product_option_id', $option->id)->get()->map(fn ($value) => $value->translate('name'))->sort()->values()->all())->toBe(['Drept', 'Lejer', 'Slim']);
});
