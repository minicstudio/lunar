<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Language;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\ApplyProfile;
use Lunar\ProductImport\Actions\DeleteProfile;
use Lunar\ProductImport\Actions\DismissProfile;
use Lunar\ProductImport\Actions\RenameProfile;
use Lunar\ProductImport\Actions\SaveProfile;
use Lunar\ProductImport\Actions\SaveSheetMapping;
use Lunar\ProductImport\Analysis\AnalysisCoordinator;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Data\Grouping;
use Lunar\ProductImport\Data\SheetMapping;
use Lunar\ProductImport\Enums\GroupingStrategy;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Models\ProductImportProfile;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    $this->productType = ProductType::factory()->create();
    $this->size = ProductOption::factory()->create(['name' => ['en' => 'Marime'], 'handle' => 'marime']);
    AiFakes::configure();
    ColumnMappingAgent::fake([AiFakes::answer([[0, 'variant.sku'], [2, 'product.name', 0.9, 'ro']])]);

    // A finished import whose "Produse" mapping is saved as a profile.
    $this->pastImport = ImportBuilder::import(ImportStatus::AwaitingMapping);
    $this->pastSheet = ImportBuilder::sheet($this->pastImport, 'Produse', [
        ['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Marime'],
        ['TB-1-S', 'TB-1', 'Tricou', '49,90', 'S'],
    ]);

    app(SaveSheetMapping::class)->handle($this->pastSheet, new SheetMapping(
        productTypeId: $this->productType->id,
        grouping: new Grouping(GroupingStrategy::ByColumn),
        columns: [
            new ColumnMapping(0, 'Cod', 'variant.sku'),
            new ColumnMapping(1, 'Cod parinte', 'group_key'),
            new ColumnMapping(2, 'Denumire', 'product.name', 'ro'),
            new ColumnMapping(3, 'Pret', 'variant.price', format: ColumnFormat::number(',', '.')),
            new ColumnMapping(4, 'Marime', 'variant.option', optionId: $this->size->id),
        ],
    ));

    $this->profile = app(SaveProfile::class)->handle($this->pastSheet->fresh(), 'Furnizor Textil SRL export');
});

/**
 * A new import from the same supplier, with reordered columns and a new "Sezon" column.
 */
function newSupplierImport(): array
{
    $import = ImportBuilder::import(ImportStatus::Analyzing);
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['DENUMIRE', 'cod', 'Cod Parinte', 'Marime', 'Pret', 'Sezon'],
        ['Rochie', 'RO-1-M', 'RO-1', 'M', '129,00', 'Vara'],
    ], 1, ['analysis_status' => null]);

    return [$import, $sheet];
}

test('a profile holds the headers, the mapping, and the options', function () {
    expect($this->profile->name)->toBe('Furnizor Textil SRL export')
        ->and($this->profile->headers)->toBe(['cod', 'cod parinte', 'denumire', 'pret', 'marime'])
        ->and($this->profile->mapping['product_type_id'])->toBe($this->productType->id)
        ->and($this->profile->mapping['columns'][4]['option_name'])->toBe('Marime')
        ->and($this->profile->options['match_key'])->toBe('sku');
});

test('a duplicate name is refused', function () {
    expect(fn () => app(SaveProfile::class)->handle($this->pastSheet->fresh(), 'furnizor textil srl export'))
        ->toThrow(ImportActionRefused::class, 'Another profile already has this name.');

    $other = ProductImportProfile::factory()->create(['name' => 'Alt furnizor']);

    expect(fn () => app(RenameProfile::class)->handle($other, 'Furnizor Textil SRL export'))
        ->toThrow(ImportActionRefused::class, 'Another profile already has this name.');

    expect(ProductImportProfile::count())->toBe(2);
});

test('a matching file is offered the profile and that sheet gets no analysis', function () {
    [$import, $sheet] = newSupplierImport();

    app(AnalysisCoordinator::class)->startForImport($import);

    $sheet->refresh();

    ColumnMappingAgent::assertNeverPrompted();

    expect($sheet->analysis_status)->toBe(SheetAnalysisStatus::ProfileOffered)
        ->and($sheet->offered_profile_id)->toBe($this->profile->id)
        ->and($sheet->offered_profile_score)->toBe(0.8333)
        ->and($import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);
});

test('applying the profile copies the mapping, leaves sezon unmapped, and counts the use', function () {
    [$import, $sheet] = newSupplierImport();
    app(AnalysisCoordinator::class)->startForImport($import);

    $dropped = app(ApplyProfile::class)->handle($sheet->fresh());

    $sheet->refresh();
    $mapping = $sheet->sheetMapping();

    ColumnMappingAgent::assertNeverPrompted();

    expect($dropped)->toBe([])
        ->and($sheet->analysis_status)->toBe(SheetAnalysisStatus::ProfileApplied)
        ->and($sheet->profile_id)->toBe($this->profile->id)
        ->and($sheet->profile->is($this->profile))->toBeTrue()
        ->and($mapping->productTypeId)->toBe($this->productType->id)
        ->and($mapping->column(0)->target)->toBe('product.name')
        ->and($mapping->column(1)->target)->toBe('variant.sku')
        ->and($mapping->column(2)->target)->toBe('group_key')
        ->and($mapping->column(3)->optionId)->toBe($this->size->id)
        ->and($mapping->column(4)->target)->toBe('variant.price')
        ->and($mapping->column(4)->format)->toEqual(ColumnFormat::number(',', '.'))
        ->and($mapping->column(5)->header)->toBe('Sezon')
        ->and($mapping->column(5)->target)->toBeNull()
        ->and($mapping->grouping->strategy)->toBe(GroupingStrategy::ByColumn)
        ->and($mapping->grouping->column)->toBe(2)
        ->and($this->profile->fresh()->usage_count)->toBe(1)
        ->and($this->profile->fresh()->last_used_at)->not->toBeNull();
});

test('applying a profile copies its weight unit', function () {
    $mapping = $this->profile->mapping;
    $mapping['weight_unit'] = 'g';
    $this->profile->update(['mapping' => $mapping]);

    [$import, $sheet] = newSupplierImport();
    app(AnalysisCoordinator::class)->startForImport($import);

    app(ApplyProfile::class)->handle($sheet->fresh());

    expect($sheet->fresh()->sheetMapping()->weightUnit)->toBe('g');
});

test('applying a profile copies the code meanings, the item parts, the image suffix, and the separators', function () {
    $headers = ['Cod', 'Denumire', 'Activ', 'Caracteristici', 'Imagini'];
    $past = ImportBuilder::sheet(ImportBuilder::import(ImportStatus::AwaitingMapping), 'export', [
        $headers,
        ['TB-1', 'Tricou', '2', 'Compoziție:Bumbac 100%:1', 'https://shop.example.com/img/12-small_default.jpg'],
    ]);

    app(SaveSheetMapping::class)->handle($past, new SheetMapping(
        productTypeId: $this->productType->id,
        columns: [
            new ColumnMapping(0, 'Cod', 'variant.sku'),
            new ColumnMapping(1, 'Denumire', 'product.name', 'ro'),
            new ColumnMapping(2, 'Activ', 'product.status', format: ColumnFormat::meanings(['1' => 'published', '2' => 'draft'])),
            new ColumnMapping(3, 'Caracteristici', 'product.properties', format: new ColumnFormat(itemParts: ['name', 'value', 'skip'], listSeparator: ';', itemSeparator: '=')),
            new ColumnMapping(4, 'Imagini', 'product.images', format: new ColumnFormat(imageSuffix: '-small_default', listSeparator: '|')),
        ],
    ));
    app(SaveProfile::class)->handle($past->fresh(), 'Export magazin');

    $import = ImportBuilder::import(ImportStatus::Analyzing);
    $sheet = ImportBuilder::sheet($import, 'export', [
        array_reverse($headers),
        ['https://shop.example.com/img/14-small_default.jpg', 'Culoare:Roșu:0', '1', 'Rochie', 'RO-1'],
    ], 1, ['analysis_status' => null]);
    app(AnalysisCoordinator::class)->startForImport($import);

    app(ApplyProfile::class)->handle($sheet->fresh());

    $mapping = $sheet->fresh()->sheetMapping();

    ColumnMappingAgent::assertNeverPrompted();

    expect($mapping->column(2)->target)->toBe('product.status')
        ->and($mapping->column(2)->format->values)->toBe(['1' => 'published', '2' => 'draft'])
        ->and($mapping->column(1)->format->itemParts)->toBe(['name', 'value', 'skip'])
        ->and($mapping->column(1)->format->listSeparator)->toBe(';')
        ->and($mapping->column(1)->format->itemSeparator)->toBe('=')
        ->and($mapping->column(0)->format->imageSuffix)->toBe('-small_default')
        ->and($mapping->column(0)->format->listSeparator)->toBe('|');
});

test('a deleted option is dropped and reported', function () {
    [$import, $sheet] = newSupplierImport();
    app(AnalysisCoordinator::class)->startForImport($import);

    $this->size->delete();

    $dropped = app(ApplyProfile::class)->handle($sheet->fresh());

    expect($dropped)->toBe(['the option "Marime"'])
        ->and($sheet->fresh()->sheetMapping()->column(3)->target)->toBeNull();
});

test('dismissing the profile sends one analysis request', function () {
    [$import, $sheet] = newSupplierImport();
    app(AnalysisCoordinator::class)->startForImport($import);

    app(DismissProfile::class)->handle($sheet->fresh());

    ColumnMappingAgent::assertPromptedTimes(1);

    expect($sheet->fresh()->offered_profile_id)->toBeNull()
        ->and($sheet->fresh()->analysis_status)->toBe(SheetAnalysisStatus::Completed)
        ->and($import->fresh()->status)->toBe(ImportStatus::AwaitingMapping);
});

test('deleting a profile keeps past mappings and stops offering it', function () {
    [$import, $sheet] = newSupplierImport();
    app(AnalysisCoordinator::class)->startForImport($import);
    app(ApplyProfile::class)->handle($sheet->fresh());

    $pastMapping = $this->pastSheet->fresh()->mapping;
    $appliedMapping = $sheet->fresh()->mapping;

    app(DeleteProfile::class)->handle($this->profile);

    expect(ProductImportProfile::count())->toBe(0)
        ->and($this->pastSheet->fresh()->mapping)->toBe($pastMapping)
        ->and($sheet->fresh()->mapping)->toBe($appliedMapping)
        ->and($sheet->fresh()->profile_id)->toBeNull();

    [$another] = newSupplierImport();
    app(AnalysisCoordinator::class)->startForImport($another);

    ColumnMappingAgent::assertPromptedTimes(1);
});
