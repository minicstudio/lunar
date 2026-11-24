<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Filament\Livewire\PreviewStep;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    $this->actingAs($this->makeStaff(admin: true), 'staff');
});

test('the preview shows the totals', function () {
    $import = ImportBuilder::import(ImportStatus::PreviewReady);
    $sheet = ProductImportSheet::factory()->for($import, 'import')->create(['header_row' => 1]);

    $rowNumber = 2;
    $statuses = [...array_fill(0, 6, RowStatus::Error), ...array_fill(0, 9, RowStatus::Warning)];
    $groups = [
        ...array_map(fn (int $i) => ['key' => "new-{$i}", 'variants' => 3, 'product' => null], range(1, 21)),
        ...array_map(fn (int $i) => ['key' => "upd-{$i}", 'variants' => $i <= 10 ? 4 : 5, 'product' => 1000 + $i], range(1, 13)),
    ];

    foreach ($groups as $group) {
        foreach (range(1, $group['variants']) as $ignored) {
            ProductImportRow::factory()->for($sheet, 'sheet')->create([
                'row_number' => $rowNumber++,
                'group_key' => $group['key'],
                'matched_product_id' => $group['product'],
                'status' => array_shift($statuses) ?? RowStatus::Valid,
            ]);
        }
    }

    $html = Livewire::test(PreviewStep::class, ['import' => $import])->html();

    foreach (['products' => 34, 'new' => 21, 'updated' => 13, 'variants' => 118, 'warnings' => 9, 'errors' => 6] as $key => $total) {
        expect($html)->toMatch('/data-total="'.$key.'"\s*>\s*'.$total.'\s*</');
    }
});

test('correcting row 37 shows the empty original cell and clears the error', function () {
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret'],
        40,
        fn (int $i) => ["SKU-{$i}", "Produs {$i}", $i + 1 === 37 ? '' : '49,90'],
    ));
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'en'], 2 => 'variant.price'], ProductType::factory()->create()->id);
    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::PreviewReady]);

    $row = ProductImportRow::query()->where('row_number', 37)->sole();

    Livewire::test(PreviewStep::class, ['import' => $import])
        ->mountTableAction('correctRow', $row)
        ->assertMountedActionModalSee('Original cell: empty')
        ->assertMountedActionModalSee('Original cell: SKU-36')
        ->setTableActionData(['fields' => ['variant__price' => '49,90']])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($row->fresh()->status)->toBe(RowStatus::Valid)
        ->and($row->fresh()->overrides)->toBe(['variant.price' => 4990]);
});

test('the start button names the variants and skips the rows with errors', function () {
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret'],
        118,
        fn (int $i) => ["SKU-{$i}", "Produs {$i}", $i <= 6 ? '' : '49,90'],
    ));
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'en'], 2 => 'variant.price'], ProductType::factory()->create()->id);
    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::PreviewReady]);

    Livewire::test(PreviewStep::class, ['import' => $import])
        ->assertSee('Skip rows with errors and import (112 variants)')
        ->callAction('skipErrors')
        ->assertSee('Import (112 variants)')
        ->assertDontSee('Skip rows with errors and import');

    expect(ProductImportRow::query()->where('skip_reason', RowSkipReason::Staff)->count())->toBe(6);
});

test('saving the correction form twice leaves the corrected values unchanged', function () {
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', [
        ['Cod', 'Denumire', 'Pret', 'Pret promotional', 'Etichete'],
        ['RV-1', 'Rochie', '', '', ''],
    ]);
    ImportBuilder::map($sheet, [
        0 => 'variant.sku', 1 => ['product.name', 'en'], 2 => 'variant.price', 3 => 'variant.sale_price', 4 => 'product.tags',
    ], ProductType::factory()->create()->id);
    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::PreviewReady]);

    $row = ProductImportRow::query()->where('row_number', 2)->sole();
    $corrected = ['variant.price' => 4990, 'variant.sale_price' => 14900, 'product.tags' => ['vara', 'bumbac']];

    Livewire::test(PreviewStep::class, ['import' => $import])
        ->mountTableAction('correctRow', $row)
        ->setTableActionData(['fields' => ['variant__price' => '49,90', 'variant__sale_price' => '149,00', 'product__tags' => 'vara, bumbac']])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($row->fresh()->overrides)->toBe($corrected);

    Livewire::test(PreviewStep::class, ['import' => $import])
        ->mountTableAction('correctRow', $row)
        ->assertTableActionDataSet(['fields.variant__price' => '49,90', 'fields.variant__sale_price' => '149,00', 'fields.product__tags' => 'vara, bumbac'])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($row->fresh()->overrides)->toBe($corrected);
});
