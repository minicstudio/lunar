<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\ProductImport\Analysis\ColumnMappingAgent;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\SheetAnalysisStatus;
use Lunar\ProductImport\Jobs\ReadImportFile;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\Support\SpreadsheetFixtures;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    AiFakes::configure();

    ColumnMappingAgent::fake(fn (string $prompt) => str_contains($prompt, '"sheet":"Outlet"')
        ? AiFakes::answer([[0, 'variant.sku'], [1, 'product.name', 0.9, 'ro']])
        : AiFakes::answer([[0, 'variant.sku'], [2, 'product.name', 0.9, 'ro']]));
});

afterEach(fn () => SpreadsheetFixtures::cleanup());

/**
 * Put a fixture file on the import disk and create an uploaded import for it.
 */
function uploadedImportFor(string $fixturePath, string $originalName, ?string $encoding = null): ProductImport
{
    $extension = pathinfo($fixturePath, PATHINFO_EXTENSION);
    $path = 'product-imports/'.uniqid().'.'.$extension;

    Storage::disk('local')->put($path, file_get_contents($fixturePath));

    $import = ProductImport::factory()->create(['status' => ImportStatus::Uploaded]);

    $import->files()->create([
        'position' => 0,
        'original_name' => $originalName,
        'disk' => 'local',
        'path' => $path,
        'encoding' => $encoding,
    ]);

    return $import;
}

/**
 * A supplier workbook: "Produse" with a title row, "Outlet", and "Info".
 */
function supplierWorkbook(): string
{
    $produse = [
        1 => ['Lista produse Furnizor Textil SRL'],
        3 => ['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Stoc', 'Marime'],
    ];

    foreach (range(1, 128) as $i) {
        $produse[3 + $i] = ["TB-{$i}", 'TB-'.($i % 34), 'Tricou '.($i % 34), '49,90', (string) ($i % 7), ['S', 'M', 'L'][$i % 3]];
    }

    $outlet = [1 => ['Cod', 'Denumire', 'Pret']];

    foreach (range(1, 40) as $i) {
        $outlet[1 + $i] = ["OUT-{$i}", "Geacă {$i}", 99.9];
    }

    return SpreadsheetFixtures::xlsx([
        'Produse' => $produse,
        'Outlet' => $outlet,
        'Info' => [1 => ['Exportat la 18.11.2026']],
    ]);
}

test('every sheet is read into the staging rows with its profile', function () {
    $import = uploadedImportFor(supplierWorkbook(), 'furnizor.xlsx');

    ReadImportFile::dispatchSync($import->id);

    $import->refresh();
    $sheets = $import->sheets()->get()->keyBy('name');

    expect($import->status)->toBe(ImportStatus::AwaitingMapping)
        ->and($sheets['Produse']->analysis_status)->toBe(SheetAnalysisStatus::Completed)
        ->and($sheets['Info']->analysis_status)->toBeNull()
        ->and($sheets->keys()->all())->toBe(['Produse', 'Outlet', 'Info'])
        ->and($sheets['Produse']->position)->toBe(0)
        ->and($sheets['Produse']->header_row)->toBe(3)
        ->and($sheets['Produse']->row_count)->toBe(128)
        ->and($sheets['Produse']->skipped)->toBeFalse()
        ->and($sheets['Outlet']->row_count)->toBe(40)
        ->and($sheets['Outlet']->skipped)->toBeFalse()
        ->and($sheets['Info']->row_count)->toBe(0)
        ->and($sheets['Info']->skipped)->toBeTrue();

    $columns = collect($sheets['Produse']->columns)->keyBy('header');

    expect($columns->keys()->all())->toBe(['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Stoc', 'Marime'])
        ->and($columns['Pret'])->not->toHaveKey('type')
        ->and($columns['Cod parinte']['fill_rate'])->toEqual(1.0)
        ->and(round($columns['Cod parinte']['distinct_share'] * 100))->toBe(27.0);
});

test('raw cells and row numbers are stored', function () {
    $import = uploadedImportFor(supplierWorkbook(), 'furnizor.xlsx');

    ReadImportFile::dispatchSync($import->id);

    $produse = ProductImportSheet::where('name', 'Produse')->first();
    $row = ProductImportRow::where('product_import_sheet_id', $produse->id)->where('row_number', 4)->first();

    expect($row->raw)->toBe(['TB-1', 'TB-1', 'Tricou 1', '49,90', '1', 'M'])
        ->and(ProductImportRow::where('product_import_sheet_id', $produse->id)->where('row_number', 2)->exists())->toBeFalse()
        ->and(ProductImportRow::where('product_import_sheet_id', $produse->id)->max('row_number'))->toBe(131)
        ->and(ProductImportRow::where('product_import_sheet_id', ProductImportSheet::where('name', 'Outlet')->value('id'))->where('row_number', 2)->first()->raw)
        ->toBe(['OUT-1', 'Geacă 1', '99.9']);
});

test('a csv file is one sheet named after the file', function () {
    $path = SpreadsheetFixtures::csv([['Cod', 'Denumire', 'Pret'], ['TB-1', 'Rochie roşie', '49,90']], ';', 'WINDOWS-1250');
    $import = uploadedImportFor($path, 'export furnizor.csv', 'Windows-1250');

    ReadImportFile::dispatchSync($import->id);

    $sheet = $import->sheets()->sole();

    expect($sheet->name)->toBe('export furnizor.csv')
        ->and($sheet->row_count)->toBe(1)
        ->and($sheet->rows()->where('row_number', 2)->first()->raw)->toBe(['TB-1', 'Rochie roşie', '49,90']);
});

test('cells the workbook stores as numbers are recorded as typed', function () {
    $import = uploadedImportFor(SpreadsheetFixtures::xlsx([
        'Produse' => [['Cod', 'Pret', 'Pret vechi'], ['TB-1', 1256.59, SpreadsheetFixtures::text('49,90')]],
    ]), 'preturi.xlsx');

    ReadImportFile::dispatchSync($import->id);

    $row = $import->sheets()->sole()->rows()->where('row_number', 2)->sole();

    expect($row->raw)->toBe(['TB-1', '1256.59', '49,90'])
        ->and($row->typed)->toBe([1]);
});

test('csv cells are never typed and keep their text', function () {
    $import = uploadedImportFor(SpreadsheetFixtures::csv([['Cod', 'Pret', 'Greutate'], ['TB-1', '1256.590000', '1.250']]), 'preturi.csv');

    ReadImportFile::dispatchSync($import->id);

    $row = $import->sheets()->sole()->rows()->where('row_number', 2)->sole();

    expect($row->raw)->toBe(['TB-1', '1256.590000', '1.250'])
        ->and($row->typed)->toBe([]);
});

test('csv imports with different encodings each read their own values', function () {
    $first = uploadedImportFor(
        SpreadsheetFixtures::csv([['Cod', 'Denumire', 'Megnevezés'], ['TB-1', 'Rochie roşie', 'Hosszú ujjú póló']], ';', 'WINDOWS-1250'),
        'textil.csv',
        'Windows-1250',
    );
    $second = uploadedImportFor(
        SpreadsheetFixtures::csv([['Cod', 'Denumire'], ['TB-2', 'Rochie roșie']], ';', 'ISO-8859-16'),
        'moda.csv',
        'ISO-8859-16',
    );

    ReadImportFile::dispatchSync($first->id);
    ReadImportFile::dispatchSync($second->id);

    expect($first->sheets()->sole()->rows()->where('row_number', 2)->sole()->raw)->toBe(['TB-1', 'Rochie roşie', 'Hosszú ujjú póló'])
        ->and($second->sheets()->sole()->rows()->where('row_number', 2)->sole()->raw)->toBe(['TB-2', 'Rochie roșie']);
});

test('a utf-8 csv with windows-1250 chosen is read unchanged', function () {
    $import = uploadedImportFor(SpreadsheetFixtures::csv([['Cod', 'Denumire'], ['TB-1', 'Rochie roșie']], ';'), 'utf8.csv', 'Windows-1250');

    ReadImportFile::dispatchSync($import->id);

    expect($import->sheets()->sole()->rows()->where('row_number', 2)->sole()->raw)->toBe(['TB-1', 'Rochie roșie']);
});

test('25,000 rows fail the import with a message naming the 20,000 row limit', function () {
    $rows = [['Cod', 'Pret']];

    foreach (range(1, 25000) as $i) {
        $rows[] = ["SKU-{$i}", '10'];
    }

    $import = uploadedImportFor(SpreadsheetFixtures::csv($rows), 'mare.csv');

    ReadImportFile::dispatchSync($import->id);

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->failure_message)->toContain('20,000')
        ->and($import->rows()->count())->toBe(0);
});

test('a workbook above the row limit fails while reading', function () {
    config(['lunar.product-import.max_rows' => 30]);

    $import = uploadedImportFor(SpreadsheetFixtures::xlsx([
        'Produse' => [['Cod', 'Pret'], ...SpreadsheetFixtures::rows(25, fn (int $i) => ["A-{$i}", 1])],
        'Outlet' => [['Cod', 'Pret'], ...SpreadsheetFixtures::rows(25, fn (int $i) => ["B-{$i}", 1])],
    ]), 'doua.xlsx');

    ReadImportFile::dispatchSync($import->id);

    expect($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->failure_message)->toContain('30');
});

test('each included sheet is analyzed once after reading', function () {
    $import = uploadedImportFor(supplierWorkbook(), 'furnizor.xlsx');

    ReadImportFile::dispatchSync($import->id);

    ColumnMappingAgent::assertPromptedTimes(2);
});

test('reading the file leaves the catalog unchanged', function () {
    $import = uploadedImportFor(supplierWorkbook(), 'furnizor.xlsx');

    ReadImportFile::dispatchSync($import->id);

    expect(Product::count())->toBe(0)
        ->and(ProductVariant::count())->toBe(0);
});

test('an import that is no longer uploaded is not read again', function () {
    $import = uploadedImportFor(supplierWorkbook(), 'furnizor.xlsx');
    $import->update(['status' => ImportStatus::Cancelled]);

    ReadImportFile::dispatchSync($import->id);

    expect($import->sheets()->count())->toBe(0);
});

test('two csv files give one sheet each, named after the files', function () {
    $import = uploadedImportFor(SpreadsheetFixtures::csv([['Product ID', 'Name'], ['12', 'Cămașă']], ';'), 'products_import.csv');
    $second = SpreadsheetFixtures::csv([['Product ID', 'Reference'], ['12', 'CAM-12-S'], ['12', 'CAM-12-M']], ';');
    $path = 'product-imports/'.uniqid().'.csv';
    Storage::disk('local')->put($path, file_get_contents($second));
    $import->files()->create(['position' => 1, 'original_name' => 'combinations_import.csv', 'disk' => 'local', 'path' => $path]);

    ReadImportFile::dispatchSync($import->id);

    $sheets = $import->sheets()->get();

    expect($sheets->pluck('name')->all())->toBe(['products_import.csv', 'combinations_import.csv'])
        ->and($sheets->pluck('row_count')->all())->toBe([1, 2])
        ->and($sheets->pluck('product_import_file_id')->all())->toBe($import->files()->pluck('id')->all())
        ->and($sheets[1]->rows()->where('row_number', 3)->sole()->raw)->toBe(['12', 'CAM-12-M']);
});

test('the sheets of a workbook are named after their file when the import has several files', function () {
    $import = uploadedImportFor(SpreadsheetFixtures::xlsx(['Produse' => [['Cod', 'Denumire'], ['TB-1', 'Tricou']]]), 'furnizor.xlsx');
    $path = 'product-imports/'.uniqid().'.csv';
    Storage::disk('local')->put($path, file_get_contents(SpreadsheetFixtures::csv([['Cod', 'Stoc'], ['TB-1', '3']])));
    $import->files()->create(['position' => 1, 'original_name' => 'stoc.csv', 'disk' => 'local', 'path' => $path]);

    ReadImportFile::dispatchSync($import->id);

    expect($import->sheets()->pluck('name')->all())->toBe(['furnizor.xlsx: Produse', 'stoc.csv']);
});

test('12,000 rows in each of two files fail the import with the 20,000 row limit', function () {
    $rows = [['Cod', 'Pret']];

    foreach (range(1, 12000) as $i) {
        $rows[] = ["SKU-{$i}", '10'];
    }

    $import = uploadedImportFor(SpreadsheetFixtures::csv($rows), 'products_import.csv');
    $path = 'product-imports/'.uniqid().'.csv';
    Storage::disk('local')->put($path, file_get_contents(SpreadsheetFixtures::csv($rows)));
    $import->files()->create(['position' => 1, 'original_name' => 'combinations_import.csv', 'disk' => 'local', 'path' => $path]);

    ReadImportFile::dispatchSync($import->id);

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->failure_message)->toContain('20,000')
        ->and($import->rows()->count())->toBe(0);
});
