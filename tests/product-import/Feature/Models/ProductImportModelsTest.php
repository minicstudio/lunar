<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\ValueType;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportFile;
use Lunar\ProductImport\Models\ProductImportProfile;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the tables exist with the lunar prefix', function () {
    $prefix = config('lunar.database.table_prefix');

    expect($prefix)->not->toBe('');

    foreach (['product_imports', 'product_import_files', 'product_import_sheets', 'product_import_rows', 'product_import_values', 'product_import_profiles'] as $table) {
        expect(Schema::hasTable($prefix.$table))->toBeTrue();
    }

    expect((new ProductImport)->getTable())->toBe($prefix.'product_imports')
        ->and((new ProductImportFile)->getTable())->toBe($prefix.'product_import_files')
        ->and((new ProductImportSheet)->getTable())->toBe($prefix.'product_import_sheets')
        ->and((new ProductImportRow)->getTable())->toBe($prefix.'product_import_rows')
        ->and((new ProductImportValue)->getTable())->toBe($prefix.'product_import_values')
        ->and((new ProductImportProfile)->getTable())->toBe($prefix.'product_import_profiles');
});

test('json columns are cast', function () {
    $import = ProductImport::factory()->create(['options' => ['match_key' => 'sku'], 'stats' => ['failed_rows' => 2]]);
    $sheet = ProductImportSheet::factory()->for($import, 'import')->create([
        'columns' => [['index' => 0, 'header' => 'SKU']],
        'mapping' => ['columns' => []],
    ]);
    $row = ProductImportRow::factory()->for($sheet, 'sheet')->create([
        'raw' => [0 => 'TB-001'],
        'normalized' => ['variant.sku' => 'TB-001'],
        'overrides' => ['variant.price' => 4990],
    ]);

    $import->refresh();
    $sheet->refresh();
    $row->refresh();

    expect($import->status)->toBe(ImportStatus::Uploaded)
        ->and($import->options)->toBe(['match_key' => 'sku'])
        ->and($sheet->columns[0]['header'])->toBe('SKU')
        ->and($row->product_import_id)->toBe($import->id)
        ->and($row->raw)->toBe(['TB-001'])
        ->and($row->effective())->toBe(['variant.sku' => 'TB-001', 'variant.price' => 4990]);
});

test('deleting an import deletes its sheets, rows, and values', function () {
    $import = ProductImport::factory()->create();
    $sheet = ProductImportSheet::factory()->for($import, 'import')->create();
    ProductImportRow::factory()->count(3)->for($sheet, 'sheet')->create();
    ProductImportValue::factory()->for($import, 'import')->create(['type' => ValueType::Brand]);

    $other = ProductImport::factory()->create();
    $otherSheet = ProductImportSheet::factory()->for($other, 'import')->create();
    ProductImportRow::factory()->for($otherSheet, 'sheet')->create();

    $import->delete();

    expect(ProductImportSheet::where('product_import_id', $import->id)->count())->toBe(0)
        ->and(ProductImportRow::where('product_import_id', $import->id)->count())->toBe(0)
        ->and(ProductImportValue::where('product_import_id', $import->id)->count())->toBe(0)
        ->and(ProductImportRow::where('product_import_id', $other->id)->count())->toBe(1);
});

test('a transition from the expected status updates the import', function () {
    $import = ProductImport::factory()->status(ImportStatus::AwaitingMapping)->create();

    expect($import->transitionTo(ImportStatus::Staging))->toBeTrue()
        ->and($import->status)->toBe(ImportStatus::Staging)
        ->and($import->fresh()->status)->toBe(ImportStatus::Staging);
});

test('a transition from an unexpected status changes nothing', function () {
    $import = ProductImport::factory()->status(ImportStatus::AwaitingMapping)->create();

    // Another request moved the import on meanwhile.
    ProductImport::whereKey($import->id)->update(['status' => ImportStatus::Staging->value]);

    expect($import->transitionTo(ImportStatus::Staging, ImportStatus::AwaitingMapping, ['failure_message' => 'x']))->toBeFalse()
        ->and($import->fresh()->status)->toBe(ImportStatus::Staging)
        ->and($import->fresh()->failure_message)->toBeNull();
});

test('a transition that is not allowed changes nothing', function () {
    $import = ProductImport::factory()->status(ImportStatus::Completed)->create();

    expect($import->transitionTo(ImportStatus::Staging))->toBeFalse()
        ->and($import->fresh()->status)->toBe(ImportStatus::Completed);
});

test('included rows are the rows below the header row of sheets that are not skipped', function () {
    $import = ProductImport::factory()->create();
    $products = ProductImportSheet::factory()->for($import, 'import')->create(['name' => 'Produse', 'header_row' => 3]);
    $info = ProductImportSheet::factory()->for($import, 'import')->create(['name' => 'Info', 'position' => 1, 'skipped' => true]);

    foreach ([1, 2, 3, 4, 5] as $rowNumber) {
        ProductImportRow::factory()->for($products, 'sheet')->create(['row_number' => $rowNumber]);
    }

    ProductImportRow::factory()->for($info, 'sheet')->create(['row_number' => 2]);

    $otherSheet = ProductImportSheet::factory()->for(ProductImport::factory(), 'import')->create();
    ProductImportRow::factory()->for($otherSheet, 'sheet')->create(['row_number' => 4]);

    $included = ProductImportRow::includedIn($import)->get();

    expect($included->pluck('product_import_sheet_id')->unique()->all())->toBe([$products->id])
        ->and($included->pluck('row_number')->sort()->values()->all())->toBe([4, 5]);
});

test('a transition saves the extra attributes', function () {
    $import = ProductImport::factory()->status(ImportStatus::Uploaded)->create();

    $import->transitionTo(ImportStatus::Failed, attributes: ['failure_message' => 'Too many rows']);

    expect($import->fresh()->failure_message)->toBe('Too many rows');
});
