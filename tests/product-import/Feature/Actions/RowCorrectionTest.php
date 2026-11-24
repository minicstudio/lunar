<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\CorrectRow;
use Lunar\ProductImport\Actions\IncludeRows;
use Lunar\ProductImport\Actions\SkipRows;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Data\ColumnMapping;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Filament\Actions\CorrectRowAction;
use Lunar\ProductImport\Filament\Livewire\PreviewStep;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Queries\PreviewSummary;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    $productType = ProductType::factory()->create();

    $this->import = ImportBuilder::import(ImportStatus::Staging);

    // 40 one-variant products; rows 37, 38, 39, 40, 41 and 12 lack a price.
    $missing = [12, 37, 38, 39, 40, 41];

    $this->sheet = ImportBuilder::sheet($this->import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret'],
        40,
        fn (int $i) => ["SKU-{$i}", "Produs {$i}", in_array($i + 1, $missing, true) ? '' : '49,90'],
    ));
    ImportBuilder::map($this->sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price'], $productType->id);

    ImportBuilder::stage($this->import);
    $this->import->update(['status' => ImportStatus::PreviewReady]);
});

function correctionRow(int $number): ProductImportRow
{
    return ProductImportRow::query()->where('row_number', $number)->firstOrFail();
}

test('entering 49,90 on row 37 clears its error, keeps the raw cell, and does not touch other groups', function () {
    expect(correctionRow(37)->status)->toBe(RowStatus::Error);

    // Mark another group's row, to see that it is not validated again.
    correctionRow(38)->update(['messages' => [['level' => 'error', 'code' => 'marker', 'field' => null, 'message' => 'untouched']]]);

    app(CorrectRow::class)->handle(correctionRow(37), ['variant.price' => '49,90']);

    $row = correctionRow(37);

    expect($row->status)->toBe(RowStatus::Valid)
        ->and($row->messages)->toBe([])
        ->and($row->overrides)->toBe(['variant.price' => 4990])
        ->and($row->raw)->toBe([0 => 'SKU-36', 1 => 'Produs 36'])
        ->and(correctionRow(38)->messages[0]['message'])->toBe('untouched');
});

test('an invalid correction is refused and not stored', function () {
    expect(fn () => app(CorrectRow::class)->handle(correctionRow(37), ['variant.price' => 'gratis']))
        ->toThrow(ValidationException::class);

    expect(correctionRow(37)->overrides)->toBeNull();
});

test('a category correction is read with its column separators and shown again in the same form', function () {
    $mapping = $this->sheet->sheetMapping();
    $mapping->columns[] = new ColumnMapping(3, 'Categorii', 'product.category', format: new ColumnFormat(listSeparator: ';', pathSeparator: '/'));
    $this->sheet->update(['mapping' => $mapping->toArray()]);

    app(CorrectRow::class)->handle(correctionRow(2), ['product.category' => 'Femei / Rochii; Rochii\\; fuste']);

    $row = correctionRow(2);

    expect($row->overrides['product.category'])->toBe([['Femei', 'Rochii'], ['Rochii; fuste']])
        ->and(CorrectRowAction::currentValues($row))->toBe(['product__category' => 'Femei / Rochii; Rochii\\; fuste']);
});

test('skipping all 6 error rows makes the error total 0', function () {
    expect(app(PreviewSummary::class)->totals($this->import)['errors'])->toBe(6);

    $skipped = app(SkipRows::class)->errors($this->import);

    $totals = app(PreviewSummary::class)->totals($this->import);

    expect($skipped)->toBe(6)
        ->and($totals['errors'])->toBe(0)
        ->and($totals['skipped'])->toBe(6)
        ->and($totals['variants'])->toBe(34);
});

test('an included row is validated again', function () {
    app(SkipRows::class)->handle($this->import, [correctionRow(37)->id]);

    expect(correctionRow(37)->status)->toBe(RowStatus::Skipped);

    app(IncludeRows::class)->handle($this->import, [correctionRow(37)->id]);

    expect(correctionRow(37)->status)->toBe(RowStatus::Error)
        ->and(correctionRow(37)->skip_reason)->toBeNull();
});

test('corrections are refused while importing', function () {
    $this->import->update(['status' => ImportStatus::Importing]);

    expect(fn () => app(CorrectRow::class)->handle(correctionRow(37), ['variant.price' => '49,90']))
        ->toThrow(ImportActionRefused::class);
});

test('correcting each value that could not be read clears its message, keeps the raw cell, and the form shows the original cell', function () {
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Export', [
        ['Cod', 'Denumire', 'Pret', 'Sfarsit promotie', 'Comenzi', 'Imagini', 'URL'],
        ['TB-1', 'Tricou', '1,25', '24.12.2026', 'maybe', 'ftp://cdn.example/1.jpg', '!!!'],
    ]);
    ImportBuilder::map($sheet, [
        0 => 'variant.sku',
        1 => ['product.name', 'ro'],
        2 => ['variant.price', null, ColumnFormat::number('.')],
        3 => ['variant.sale_ends', null, ColumnFormat::date('Y-m-d G:i:s')],
        4 => 'variant.backorders',
        5 => 'product.images',
        6 => ['product.slug', 'ro'],
    ], ProductType::factory()->create()->id);
    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::PreviewReady]);

    $row = ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', 2)->firstOrFail();

    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    Livewire::test(PreviewStep::class, ['import' => $import])
        ->mountTableAction('correctRow', $row)
        ->assertMountedActionModalSee([
            'Original cell: 1,25',
            'Original cell: 24.12.2026',
            'Original cell: maybe',
            'Original cell: ftp://cdn.example/1.jpg',
            'Original cell: !!!',
        ]);

    app(CorrectRow::class)->handle($row, [
        'variant.price' => '1.25',
        'variant.sale_ends' => '2026-12-24 23:59:59',
        'variant.backorders' => 'da',
        'product.images' => 'https://cdn.example/1.jpg',
        'product.slug.ro' => 'Tricou alb',
    ]);

    $row->refresh();

    expect(collect($row->messages)->whereIn('level', ['error', 'warning'])->pluck('code')->all())->not->toContain('not_in_format', 'not_date', 'not_recognized', 'image_not_web', 'slug_empty')
        ->and($row->overrides)->toMatchArray([
            'variant.price' => 125,
            'variant.backorders' => true,
            'product.images' => ['https://cdn.example/1.jpg'],
            'product.slug.ro' => 'tricou-alb',
        ])
        ->and($row->raw)->toBe([0 => 'TB-1', 1 => 'Tricou', 2 => '1,25', 3 => '24.12.2026', 4 => 'maybe', 5 => 'ftp://cdn.example/1.jpg', 6 => '!!!']);
});
