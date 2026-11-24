<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Attribute;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\ChangeSheetProductType;
use Lunar\ProductImport\Actions\SaveSheetMapping;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\Grouping;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\ExistingVariants;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\MatchKey;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    $this->group = CollectionGroup::factory()->create();

    $this->clothing = ProductType::factory()->create(['name' => 'Imbracaminte']);
    $this->accessories = ProductType::factory()->create(['name' => 'Accesorii']);

    $this->material = Attribute::factory()->create(['handle' => 'material', 'type' => TranslatedText::class, 'name' => ['en' => 'Material']]);
    $this->clothing->mappedAttributes()->attach($this->material->id);

    $this->import = ImportBuilder::import(ImportStatus::AwaitingMapping);
    $this->sheet = ImportBuilder::sheet($this->import, 'Produse', [
        ['Cod', 'Cod parinte', 'Denumire', 'Material', 'Marime'],
        ['TB-1-S', 'TB-1', 'Tricou', 'Bumbac', 'S'],
    ]);
});

function produseMapping(int $productTypeId): SheetMapping
{
    return new SheetMapping(
        productTypeId: $productTypeId,
        grouping: new Grouping(GroupingStrategy::ByColumn),
        columns: [
            new ColumnMapping(0, 'Cod', 'variant.sku', confidence: 0.98),
            new ColumnMapping(1, 'Cod parinte', 'group_key', confidence: 0.74, flagged: true),
            new ColumnMapping(2, 'Denumire', 'product.name', 'ro'),
            new ColumnMapping(3, 'Material', 'attribute.material', 'ro'),
            new ColumnMapping(4, 'Marime', 'variant.option'),
        ],
    );
}

test('the mapping and the options round-trip', function () {
    $options = new ImportOptions(matchKey: MatchKey::Ean, existingVariants: ExistingVariants::Skip, collectionGroupId: $this->group->id);

    app(SaveSheetMapping::class)->handle($this->sheet, produseMapping($this->clothing->id), $options);

    $sheet = $this->sheet->fresh();
    $mapping = $sheet->sheetMapping();

    expect($mapping->productTypeId)->toBe($this->clothing->id)
        ->and($mapping->headerRow)->toBe(1)
        ->and($mapping->grouping->strategy)->toBe(GroupingStrategy::ByColumn)
        ->and($mapping->grouping->column)->toBe(1)
        ->and($mapping->column(2)->target)->toBe('product.name')
        ->and($mapping->column(2)->locale)->toBe('ro')
        ->and($mapping->column(1)->flagged)->toBeTrue()
        ->and($this->import->fresh()->importOptions()->toArray())->toBe($options->toArray());
});

test('a new option is named after the column header by default', function () {
    app(SaveSheetMapping::class)->handle($this->sheet, produseMapping($this->clothing->id));

    $column = $this->sheet->fresh()->sheetMapping()->column(4);

    expect($column->optionId)->toBeNull()
        ->and($column->optionName)->toBe('Marime');
});

test('changing the product type unmaps material and reports it', function () {
    app(SaveSheetMapping::class)->handle($this->sheet, produseMapping($this->clothing->id));

    $dropped = app(ChangeSheetProductType::class)->handle($this->sheet->fresh(), $this->accessories->id);

    $mapping = $this->sheet->fresh()->sheetMapping();

    expect($dropped)->toBe([$this->material->attributeGroup->translate('name').' › Material'])
        ->and($mapping->productTypeId)->toBe($this->accessories->id)
        ->and($mapping->column(3)->target)->toBeNull()
        ->and($mapping->column(2)->target)->toBe('product.name');
});

test('an invalid mapping is not stored', function () {
    $mapping = produseMapping($this->clothing->id);
    $mapping->columns[2] = new ColumnMapping(2, 'Denumire', 'variant.sku');

    expect(fn () => app(SaveSheetMapping::class)->handle($this->sheet, $mapping))
        ->toThrow(ImportActionRefused::class, 'Variant SKU is mapped twice.');

    expect($this->sheet->fresh()->mapping)->toBeNull();
});

test('a save during staging is refused and the stored mapping stays', function () {
    app(SaveSheetMapping::class)->handle($this->sheet, produseMapping($this->clothing->id));
    $stored = $this->sheet->fresh()->mapping;

    $this->import->update(['status' => ImportStatus::Staging]);

    $changed = produseMapping($this->clothing->id);
    $changed->columns[4] = new ColumnMapping(4, 'Marime', 'ignore');

    expect(fn () => app(SaveSheetMapping::class)->handle($this->sheet->fresh(), $changed))
        ->toThrow(ImportActionRefused::class, 'The import is being processed. Wait until it has finished.');

    expect($this->sheet->fresh()->mapping)->toBe($stored);
});
