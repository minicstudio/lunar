<?php

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\Grouping;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\NewProductStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Filament\Livewire\SheetMappingStep;
use Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages\ViewProductImport;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportProfile;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    AiFakes::configure();
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    $this->productType = ProductType::factory()->create();
    $this->import = ImportBuilder::import(ImportStatus::AnalysisFailed);

    $this->produse = ImportBuilder::sheet($this->import, 'Produse', [
        ['Cod', 'Denumire', 'Pret'],
        ['TB-1', 'Tricou', '49,90'],
    ], 1, [
        'analysis_status' => SheetAnalysisStatus::Failed,
        'analysis_attempts' => 3,
        'analysis_error' => [
            'code' => 'provider_timeout',
            'message' => 'The AI provider did not respond. This is usually temporary. Try again.',
            'raw' => null,
            'attempt' => 3,
        ],
    ]);

    $this->outlet = ImportBuilder::sheet($this->import, 'Outlet', [
        ['Cod', 'Cod parinte', 'Denumire'],
        ['OUT-1', 'O-1', 'Geacă'],
    ], 1, [
        'mapping' => (new SheetMapping(
            productTypeId: $this->productType->id,
            grouping: new Grouping(GroupingStrategy::ByColumn, 1, 0.74),
            columns: [
                new ColumnMapping(0, 'Cod', 'variant.sku', confidence: 0.97, suggestedTarget: 'variant.sku'),
                new ColumnMapping(1, 'Cod parinte', 'group_key', confidence: 0.74, suggestedTarget: 'group_key', flagged: true),
                new ColumnMapping(2, 'Denumire', 'product.name', 'en', confidence: 0.96, suggestedTarget: 'product.name'),
            ],
        ))->toArray(),
    ]);
});

test('the flagged badge shows on the 74% column', function () {
    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $this->outlet->id)
        ->assertSee('74%')
        ->assertSee('Check this')
        ->assertSee('97%');
});

test('the failed sheet shows the error panel with the code, the attempt, and retry', function () {
    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $this->produse->id)
        ->assertSee('The AI provider did not respond. This is usually temporary. Try again.')
        ->assertSee('provider_timeout')
        ->assertSee('Attempt 3')
        ->assertSee('Retry');
});

test('continue is disabled until the failed sheet is retried or skipped', function () {
    $component = Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->assertActionDisabled('continue')
        ->assertSee('Skip sheet');

    $component->call('skipSheet', $this->produse->id)
        ->assertActionEnabled('continue');

    expect($this->import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);
});

test('retrying the failed sheet enables continue', function () {
    ColumnMappingAgent::fake([AiFakes::answer([[0, 'variant.sku'], [1, 'product.name', 0.95, 'en']])]);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->assertActionDisabled('continue')
        ->call('retryAnalysis', $this->produse->id)
        ->assertActionEnabled('continue');

    expect($this->produse->fresh()->analysis_status)->toBe(SheetAnalysisStatus::Completed);
});

test('applying the offered profile fills the mapping', function () {
    $profile = ProductImportProfile::factory()->create([
        'name' => 'Furnizor Textil',
        'headers' => ['cod', 'denumire', 'pret'],
        'mapping' => (new SheetMapping(productTypeId: $this->productType->id, columns: [
            new ColumnMapping(0, 'Cod', 'variant.sku'),
            new ColumnMapping(1, 'Denumire', 'product.name', 'en'),
            new ColumnMapping(2, 'Pret', 'variant.price'),
        ]))->toArray(),
    ]);

    $this->produse->update([
        'analysis_status' => SheetAnalysisStatus::ProfileOffered,
        'analysis_error' => null,
        'offered_profile_id' => $profile->id,
        'offered_profile_score' => 1.0,
    ]);
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $this->produse->id)
        ->assertSee('The saved profile "Furnizor Textil" matches 100% of the headers.')
        ->call('applyProfile', $this->produse->id)
        ->assertSet("sheets.{$this->produse->id}.columns.2.target", 'variant.price')
        ->assertSet("sheets.{$this->produse->id}.product_type_id", $this->productType->id);

    expect($this->produse->fresh()->analysis_status)->toBe(SheetAnalysisStatus::ProfileApplied);
});

test('a changed target is saved and a duplicate is reported', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $this->outlet->id)
        ->set("sheets.{$this->outlet->id}.columns.2.target", 'variant.sku')
        ->assertSee('Variant SKU is mapped twice.')
        ->assertActionDisabled('continue')
        ->set("sheets.{$this->outlet->id}.columns.2.target", 'product.name')
        ->assertDontSee('Variant SKU is mapped twice.');

    expect($this->outlet->fresh()->sheetMapping()->column(2)->target)->toBe('product.name');
});

test('a mapped weight column asks for the weight unit of the store units', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);

    $component = Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $this->outlet->id)
        ->assertDontSee('Weight unit')
        ->set("sheets.{$this->outlet->id}.columns.2.target", 'variant.weight')
        ->set("sheets.{$this->outlet->id}.columns.2.decimal", 'dot')
        ->set("sheets.{$this->outlet->id}.columns.2.thousands", 'none')
        ->assertSee('Weight unit')
        ->assertSeeHtml(['<option value="kg">', '<option value="g">', '<option value="lbs">'])
        ->assertSee('Choose the weight unit of the sheet &quot;Outlet&quot;.', false)
        ->assertActionDisabled('continue');

    expect($this->outlet->fresh()->sheetMapping()->column(2)->target)->toBe('product.name');

    $component->set("sheets.{$this->outlet->id}.weight_unit", 'g')
        ->assertDontSee('Choose the weight unit of the sheet');

    expect($this->outlet->fresh()->sheetMapping())
        ->column(2)->target->toBe('variant.weight')
        ->weightUnit->toBe('g');
});

test('the status option offers draft, published, and the status column', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->assertSeeHtml(['<option value="draft">', '<option value="published">', '<option value="from_column">'])
        ->assertSee('From the status column')
        ->call('skipSheet', $this->produse->id)
        ->call('selectSheet', $this->outlet->id)
        ->set('options.new_product_status', 'from_column')
        ->assertSee('New products take their status from the status column, but no included sheet maps a status column.');

    expect($this->import->fresh()->importOptions()->newProductStatus)->toBe(NewProductStatus::FromColumn);
});

test('the additional collections are a searchable multi-select that is saved with the options', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $group = CollectionGroup::factory()->create(['name' => 'Principal']);
    $all = Collection::factory()->create(['collection_group_id' => $group->id, 'attribute_data' => collect(['name' => new Text('Toate categoriile')])]);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $this->outlet->id)
        ->assertFormFieldExists('additional_collection_ids', 'optionsForm', fn (Select $field): bool => $field->isMultiple()
            && $field->isSearchable()
            && $field->getOptionLabels() === []
            && $field->getLabel() === 'Additional collections')
        ->set('options.additional_collection_ids', [(string) $all->id])
        ->assertFormFieldExists('additional_collection_ids', 'optionsForm', fn (Select $field): bool => $field->getOptionLabels() === [$all->id => 'Principal > Toate categoriile']);

    expect($this->import->fresh()->importOptions()->additionalCollectionIds)->toBe([$all->id]);
});

test('linking images to variants is an option that is off by default and saved when turned on', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);

    expect($this->import->importOptions()->linkVariantImages)->toBeFalse();

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $this->outlet->id)
        ->assertFormFieldExists('link_variant_images', 'optionsForm', fn (Toggle $field): bool => $field->getLabel() === 'Link images to variants')
        ->set('options.link_variant_images', true);

    expect($this->import->fresh()->importOptions()->linkVariantImages)->toBeTrue();
});

test('the import page refreshes itself while staging', function () {
    $this->import->update(['status' => ImportStatus::Staging]);

    Livewire::test(ViewProductImport::class, ['record' => $this->import->id])
        ->assertSeeHtml('wire:poll.3s')
        ->assertSee('Staging');
});

/**
 * An export sheet whose proposal gave each number column its format.
 */
function formatsMappedSheet(ProductImport $import, int $productTypeId): ProductImportSheet
{
    return ImportBuilder::sheet($import, 'product-export', [
        ['SKU', 'Regular price', 'Weight (kg)', 'Stock', 'Greutate'],
        ['TB-1', '1256,59', '1.250', '5', '1,25'],
    ], 1, [
        'mapping' => (new SheetMapping(
            productTypeId: $productTypeId,
            weightUnit: 'kg',
            columns: [
                new ColumnMapping(0, 'SKU', 'variant.sku', confidence: 0.97, suggestedTarget: 'variant.sku'),
                new ColumnMapping(1, 'Regular price', 'variant.price', confidence: 0.95, suggestedTarget: 'variant.price', format: ColumnFormat::number(',')),
                new ColumnMapping(2, 'Weight (kg)', 'variant.weight', confidence: 0.95, suggestedTarget: 'variant.weight', format: ColumnFormat::number('.')),
                new ColumnMapping(3, 'Stock', confidence: 0.4, suggestedTarget: 'variant.stock'),
                new ColumnMapping(4, 'Greutate', 'ignore', confidence: 0.95, suggestedTarget: 'ignore'),
            ],
        ))->toArray(),
    ]);
}

test('the proposal shows each number column with its separators', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $sheet = formatsMappedSheet($this->import, $this->productType->id);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->assertSet("sheets.{$sheet->id}.columns.1.decimal", 'comma')
        ->assertSet("sheets.{$sheet->id}.columns.1.thousands", 'none')
        ->assertSet("sheets.{$sheet->id}.columns.2.decimal", 'dot')
        ->assertSet("sheets.{$sheet->id}.columns.2.thousands", 'none')
        ->assertSeeHtml('wire:model.live="sheets.'.$sheet->id.'.columns.1.decimal"')
        ->assertSeeHtml('wire:model.live="sheets.'.$sheet->id.'.columns.2.thousands"')
        ->assertSee('Decimal separator')
        ->assertSee('Thousands separator');
});

test('changing the weight decimal separator saves it', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $sheet = formatsMappedSheet($this->import, $this->productType->id);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->set("sheets.{$sheet->id}.columns.2.decimal", 'comma');

    expect($sheet->fresh()->sheetMapping()->column(2)->format)->toEqual(ColumnFormat::number(',', ''))
        ->and($sheet->fresh()->sheetMapping()->column(1)->format)->toEqual(ColumnFormat::number(',', ''));
});

test('a flagged format is highlighted until staff change it', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $sheet = formatsMappedSheet($this->import, $this->productType->id);
    $mapping = $sheet->sheetMapping();
    $mapping->column(2)->flagged = true;
    $sheet->update(['mapping' => $mapping->toArray()]);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->assertSee('Check the format: not every sample reads in it.')
        ->set("sheets.{$sheet->id}.columns.2.decimal", 'comma')
        ->assertDontSee('Check the format: not every sample reads in it.');
});

test('continue is disabled while the stock column has no format', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $this->produse->update(['skipped' => true]);
    $this->outlet->update(['skipped' => true]);
    $sheet = formatsMappedSheet($this->import, $this->productType->id);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->assertActionEnabled('continue')
        ->set("sheets.{$sheet->id}.columns.3.target", 'variant.stock')
        ->assertActionDisabled('continue')
        ->assertSee('Choose the number format of the column &quot;Stock&quot; on the sheet &quot;product-export&quot;.', false)
        ->set("sheets.{$sheet->id}.columns.3.decimal", 'comma')
        ->set("sheets.{$sheet->id}.columns.3.thousands", 'dot')
        ->assertActionEnabled('continue');

    expect($sheet->fresh()->sheetMapping()->column(3)->format)->toEqual(ColumnFormat::number(',', '.'));
});

/**
 * An export sheet whose proposal gave the status codes their meanings, the
 * features column its item parts (flagged), and the image column its size
 * suffix.
 */
function conventionsMappedSheet(ProductImport $import, int $productTypeId): ProductImportSheet
{
    return ImportBuilder::sheet($import, 'product-export', [
        ['SKU', 'Name', 'Published', 'Features', 'Images'],
        ['TB-1', 'Tricou', '2', 'Compoziție:Bumbac 100%:1', 'https://shop.example.com/img/12-small_default.jpg'],
    ], 1, [
        'mapping' => (new SheetMapping(
            productTypeId: $productTypeId,
            columns: [
                new ColumnMapping(0, 'SKU', 'variant.sku', confidence: 0.97, suggestedTarget: 'variant.sku'),
                new ColumnMapping(1, 'Name', 'product.name', 'en', confidence: 0.96, suggestedTarget: 'product.name'),
                new ColumnMapping(2, 'Published', 'product.status', confidence: 0.95, suggestedTarget: 'product.status', format: ColumnFormat::meanings(['1' => 'published', '2' => 'draft'])),
                new ColumnMapping(3, 'Features', 'product.properties', confidence: 0.95, suggestedTarget: 'product.properties', flagged: true, format: new ColumnFormat(itemParts: ['name', 'value'], listSeparator: ',', itemSeparator: ':')),
                new ColumnMapping(4, 'Images', 'product.images', confidence: 0.95, suggestedTarget: 'product.images', format: new ColumnFormat(imageSuffix: '-small_default', listSeparator: 'none')),
            ],
        ))->toArray(),
    ]);
}

test('the code meanings, item parts, and image suffix are shown prefilled, and a flagged column is highlighted until staff change it', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $sheet = conventionsMappedSheet($this->import, $this->productType->id);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->assertSet("sheets.{$sheet->id}.columns.2.values", [['code' => '1', 'meaning' => 'published'], ['code' => '2', 'meaning' => 'draft']])
        ->assertSet("sheets.{$sheet->id}.columns.3.item_parts", ['name', 'value'])
        ->assertSet("sheets.{$sheet->id}.columns.4.image_suffix", '-small_default')
        ->assertSee(['Code meanings', 'Item parts', 'Image size suffix', 'Leave unset'])
        ->assertSeeHtml('wire:model.live="sheets.'.$sheet->id.'.columns.3.item_parts.1"')
        ->assertSeeHtml('fi-invalid')
        ->call('addItemPart', $sheet->id, 3)
        ->assertSet("sheets.{$sheet->id}.columns.3.item_parts", ['name', 'value', 'skip'])
        ->assertDontSeeHtml('fi-invalid');

    expect($sheet->fresh()->sheetMapping()->column(3)->format->itemParts)->toBe(['name', 'value', 'skip']);
});

test('adding the code -1 as draft saves it and sends no prompt', function () {
    ColumnMappingAgent::fake();
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $sheet = conventionsMappedSheet($this->import, $this->productType->id);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->call('addMeaning', $sheet->id, 2)
        ->assertSet("sheets.{$sheet->id}.columns.2.values.2", ['code' => '', 'meaning' => ''])
        ->set("sheets.{$sheet->id}.columns.2.values.2.code", '-1')
        ->set("sheets.{$sheet->id}.columns.2.values.2.meaning", 'draft');

    expect($sheet->fresh()->sheetMapping()->column(2)->format->values)->toBe(['1' => 'published', '2' => 'draft', '-1' => 'draft']);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->call('removeMeaning', $sheet->id, 2, 0);

    expect($sheet->fresh()->sheetMapping()->column(2)->format->values)->toBe(['2' => 'draft', '-1' => 'draft']);

    ColumnMappingAgent::assertNeverPrompted();
});

test('clearing the image suffix saves it', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $sheet = conventionsMappedSheet($this->import, $this->productType->id);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->set("sheets.{$sheet->id}.columns.4.image_suffix", '');

    expect($sheet->fresh()->sheetMapping()->column(4)->format->imageSuffix)->toBeNull()
        ->and($sheet->fresh()->sheetMapping()->column(4)->format->listSeparator)->toBe('none')
        ->and($sheet->fresh()->sheetMapping()->column(2)->format->values)->toBe(['1' => 'published', '2' => 'draft']);
});

test('the row type is offered on every sheet without any other setting', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $this->outlet->id)
        ->assertSeeHtml('<option value="source.type">')
        ->assertDontSeeHtml('sheets.'.$this->outlet->id.'.source"');
});

/**
 * A sheet whose proposal gave its category, tags, and options columns their
 * separators, and left its images column without one.
 */
function separatorsMappedSheet(ProductImport $import, int $productTypeId): ProductImportSheet
{
    return ImportBuilder::sheet($import, 'product-export', [
        ['SKU', 'Name', 'Categories', 'Tags', 'Options', 'Images'],
        ['TB-1', 'Tricou', 'Femei/Rochii|Femei/Noutăți', 'vara; bumbac', 'Size=M; Color=Red', 'https://cdn.example/1.jpg'],
    ], 1, [
        'mapping' => (new SheetMapping(
            productTypeId: $productTypeId,
            columns: [
                new ColumnMapping(0, 'SKU', 'variant.sku', confidence: 0.97, suggestedTarget: 'variant.sku'),
                new ColumnMapping(1, 'Name', 'product.name', 'en', confidence: 0.96, suggestedTarget: 'product.name'),
                new ColumnMapping(2, 'Categories', 'product.category', confidence: 0.95, suggestedTarget: 'product.category', format: new ColumnFormat(listSeparator: '|', pathSeparator: '/')),
                new ColumnMapping(3, 'Tags', 'product.tags', confidence: 0.95, suggestedTarget: 'product.tags', flagged: true, format: new ColumnFormat(listSeparator: ',')),
                new ColumnMapping(4, 'Options', 'variant.options', confidence: 0.95, suggestedTarget: 'variant.options', format: new ColumnFormat(listSeparator: ';', itemSeparator: '=')),
                new ColumnMapping(5, 'Images', 'product.images', confidence: 0.95, suggestedTarget: 'product.images'),
            ],
        ))->toArray(),
    ]);
}

test('each list, category, and packed-items column shows its own separators prefilled', function () {
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $sheet = separatorsMappedSheet($this->import, $this->productType->id);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->assertSet("sheets.{$sheet->id}.columns.2.list_separator", '|')
        ->assertSet("sheets.{$sheet->id}.columns.2.path_separator", '/')
        ->assertSet("sheets.{$sheet->id}.columns.3.list_separator", ',')
        ->assertSet("sheets.{$sheet->id}.columns.4.item_separator", '=')
        ->assertSeeHtml('wire:model.blur="sheets.'.$sheet->id.'.columns.2.path_separator"')
        ->assertSeeHtml('wire:model.blur="sheets.'.$sheet->id.'.columns.4.item_separator"')
        ->assertSeeHtml('wire:model.blur="sheets.'.$sheet->id.'.columns.5.list_separator"')
        ->assertDontSeeHtml('wire:model.blur="sheets.'.$sheet->id.'.columns.3.path_separator"')
        ->assertDontSeeHtml('wire:model.blur="sheets.'.$sheet->id.'.columns.0.list_separator"')
        ->assertDontSeeHtml('options.path_separator')
        ->assertDontSeeHtml('options.list_separator')
        ->assertSee(['List separator', 'Category level separator', 'Item part separator']);
});

test('continue is disabled while a list column has no separator, and a changed separator is saved without a prompt', function () {
    ColumnMappingAgent::fake();
    $this->import->update(['status' => ImportStatus::AwaitingMapping]);
    $this->outlet->update(['skipped' => true]);
    $this->produse->update(['skipped' => true]);
    $sheet = separatorsMappedSheet($this->import, $this->productType->id);

    Livewire::test(SheetMappingStep::class, ['import' => $this->import])
        ->call('selectSheet', $sheet->id)
        ->set("sheets.{$sheet->id}.columns.3.list_separator", ';')
        ->assertActionDisabled('continue')
        ->assertSee('Choose the list separator of the column &quot;Images&quot; on the sheet &quot;product-export&quot;.', false)
        ->set("sheets.{$sheet->id}.columns.5.list_separator", 'none')
        ->assertActionEnabled('continue');

    $mapping = $sheet->fresh()->sheetMapping();

    expect($mapping->column(3)->format->listSeparator)->toBe(';')
        ->and($mapping->column(3)->flagged)->toBeFalse()
        ->and($mapping->column(5)->format->listSeparator)->toBe('none');

    ColumnMappingAgent::assertNeverPrompted();
});
