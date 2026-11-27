<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Exports\ErrorReport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function reportCsv($import): array
{
    $handle = fopen('php://temp', 'w+');
    app(ErrorReport::class)->write($import, $handle);
    rewind($handle);

    $content = stream_get_contents($handle);
    fclose($handle);

    $lines = array_map(fn (string $line) => str_getcsv($line, ',', '"', '\\'), array_filter(explode("\n", substr($content, 3))));

    return array_values($lines);
}

test('7 failed rows give 7 data lines with sheet, row number, original cells, and messages', function () {
    $import = ImportBuilder::import(ImportStatus::CompletedWithErrors);
    $sheet = ImportBuilder::sheet($import, 'Produse', ImportBuilder::rows(['Cod', 'Denumire', 'Pret'], 10, fn (int $i) => ["SKU-{$i}", "Produs {$i}", '10']));
    $outlet = ImportBuilder::sheet($import, 'Outlet', [['Cod', 'Stoc'], ['OUT-1', '3']]);

    ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', '<=', 7)
        ->update(['status' => RowStatus::Failed, 'messages' => json_encode([['level' => 'error', 'code' => 'commit_failed', 'field' => null, 'message' => 'Writing this product failed: timeout']])]);
    ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', '>', 8)->update(['status' => RowStatus::Imported]);
    ProductImportRow::query()->where('product_import_sheet_id', $outlet->id)->where('row_number', 2)
        ->update(['status' => RowStatus::Failed, 'messages' => json_encode([['level' => 'error', 'code' => 'commit_failed', 'field' => null, 'message' => 'Writing this product failed: deadlock']])]);
    ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', 8)
        ->update(['status' => RowStatus::Skipped, 'skip_reason' => RowSkipReason::Existing->value, 'messages' => json_encode([['level' => 'info', 'code' => 'skip_existing', 'field' => null, 'message' => 'exists']])]);

    $lines = reportCsv($import);

    expect($lines[0])->toBe(['Sheet', 'Row', 'Cod', 'Denumire', 'Pret', 'Stoc', 'Messages'])
        ->and(count($lines) - 1)->toBe(7)
        ->and($lines[1])->toBe(['Produse', '2', 'SKU-1', 'Produs 1', '10', '', 'Writing this product failed: timeout'])
        ->and($lines[7])->toBe(['Outlet', '2', 'OUT-1', '', '', '3', 'Writing this product failed: deadlock']);
});

test('rows skipped because of errors are in the report', function () {
    $import = ImportBuilder::import(ImportStatus::Completed);
    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Pret'], ['SKU-1', '']]);

    ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', 2)->update([
        'status' => RowStatus::Skipped,
        'skip_reason' => RowSkipReason::Staff->value,
        'messages' => json_encode([['level' => 'error', 'code' => 'missing', 'field' => 'variant.price', 'message' => 'Missing price']]),
    ]);

    expect(reportCsv($import)[1])->toBe(['Produse', '2', 'SKU-1', '', 'Missing price']);
});

test('a pruned import has no report', function () {
    $import = ImportBuilder::import(ImportStatus::CompletedWithErrors, ['pruned_at' => now()]);

    expect(fn () => reportCsv($import))->toThrow(ImportActionRefused::class, 'The row data of this import was removed.');
});

test('imported rows with a failed image are in the report with the image warning', function () {
    $import = ImportBuilder::import(ImportStatus::Completed);
    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Imagini'], ['SKU-1', 'https://img.example.com/missing.jpg'], ['SKU-2', 'https://img.example.com/ok.jpg']]);
    $rows = ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', '>', 1)->orderBy('row_number')->get();
    $message = 'The image "https://img.example.com/missing.jpg" could not be downloaded: the server answered with status 404';

    $rows[0]->update(['status' => RowStatus::Imported, 'messages' => [['level' => 'warning', 'code' => 'image_failed', 'field' => 'product.images', 'message' => $message]]]);
    $rows[1]->update(['status' => RowStatus::Imported]);

    ProductImportImage::factory()->create(['product_import_id' => $import->id, 'product_id' => 1, 'product_import_row_id' => $rows[0]->id, 'url' => 'https://img.example.com/missing.jpg', 'status' => ImageStatus::Failed]);
    ProductImportImage::factory()->create(['product_import_id' => $import->id, 'product_id' => 1, 'product_import_row_id' => $rows[1]->id, 'url' => 'https://img.example.com/ok.jpg', 'status' => ImageStatus::Downloaded]);

    expect(array_slice(reportCsv($import), 1))->toBe([['Produse', '2', 'SKU-1', 'https://img.example.com/missing.jpg', $message]]);
});
