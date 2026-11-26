<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Actions\SkipRows;
use Lunar\ProductImport\Data\ImportOptions;
use Lunar\ProductImport\Enums\ExistingVariants;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowRole;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Filament\Livewire\PreviewStep;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\Tests\ProductImport\Support\Discounts;
use Lunar\Tests\ProductImport\Support\GroupedRows;
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

test('include shows on a row that its type skipped and on a staff-skipped row, not on a row skipped as an existing variant', function () {
    ProductVariant::factory()->for(Product::factory(), 'product')->create(['sku' => 'CARD-10']);

    $import = GroupedRows::import(ProductType::factory()->create()->id, [
        3 => ['10', 'simple', '', 'CARD-10', 'Card cadou', '100,00', '3', ''],
        4 => ['60', 'simple', '', 'GP-60', 'Geacă puf', '1256,59', '2', ''],
    ], new ImportOptions(existingVariants: ExistingVariants::Skip), 'en');

    app(SkipRows::class)->handle($import, [GroupedRows::row($import, 4)->id]);

    [$grouped, $existing, $staff] = [GroupedRows::row($import, 2), GroupedRows::row($import, 3), GroupedRows::row($import, 4)];

    expect([$grouped->skip_reason, $existing->skip_reason, $staff->skip_reason])
        ->toBe([RowSkipReason::RowType, RowSkipReason::Existing, RowSkipReason::Staff]);

    Livewire::test(PreviewStep::class, ['import' => $import])
        ->assertTableActionVisible('includeRowAs', $grouped)
        ->assertTableActionHidden('includeRow', $grouped)
        ->assertTableActionVisible('includeRow', $staff)
        ->assertTableActionHidden('includeRowAs', $staff)
        ->assertTableActionHidden('includeRow', $existing)
        ->assertTableActionHidden('includeRowAs', $existing)
        ->mountTableAction('includeRowAs', $grouped)
        ->assertMountedActionModalSee('Include export, row 2')
        ->assertTableActionDataSet(['role' => RowRole::ProductAndVariant->value])
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($grouped->fresh()->only(['role', 'role_override', 'skip_reason']))
        ->toBe(['role' => RowRole::ProductAndVariant, 'role_override' => RowRole::ProductAndVariant, 'skip_reason' => null]);
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

test('each variant shows its discount next to its price, and a discount that ended shows none', function () {
    $this->travelTo(now()->setDate(2026, 11, 24));

    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = Discounts::sheet($import, ProductType::factory()->create()->id, [
        2 => ['OUT-RUHA-S', 'Rochie S', '229,00', '', '30', '', '2026-12-31'],
        3 => ['OUT-VECHI', 'Bluză', '99,00', '', '30', '', '2026-11-01'],
    ]);
    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::PreviewReady]);

    $current = ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', 2)->sole();
    $ended = ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', 3)->sole();

    Livewire::test(PreviewStep::class, ['import' => $import])
        ->assertSee('Discount')
        ->assertTableColumnStateSet('discount', '30% · until 31 Dec 2026', $current)
        ->assertTableColumnStateSet('discount', null, $ended)
        ->assertSee('30% · until 31 Dec 2026');
});
