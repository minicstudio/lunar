<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Models\Product;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Filament\Livewire\ImportResult;
use Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages\ViewProductImport;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    $this->actingAs($this->makeStaff(admin: true), 'staff');
});

function finishedWithFailures(array $attributes = [])
{
    $import = ImportBuilder::import(ImportStatus::CompletedWithErrors, [
        'stats' => ['new_products' => 88, 'updated_products' => 13, 'failed_rows' => 1, 'created' => ['brands' => [['id' => 1, 'name' => 'Furnizor Textil']]]],
        'finished_at' => now(),
        ...$attributes,
    ]);
    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Pret'], ['SKU-1', '10']]);

    ProductImportRow::query()->where('product_import_sheet_id', $sheet->id)->where('row_number', 2)->update([
        'status' => RowStatus::Failed,
        'match_value' => 'SKU-1',
        'messages' => json_encode([['level' => 'error', 'code' => 'commit_failed', 'field' => null, 'message' => 'Writing this product failed: timeout']]),
    ]);

    return $import;
}

test('a finished import shows its statistics, created records, failed rows, the report, and the retry', function () {
    $import = finishedWithFailures();

    Livewire::test(ImportResult::class, ['import' => $import])
        ->assertSee('88')
        ->assertSee('Furnizor Textil')
        ->assertSee('Writing this product failed: timeout')
        ->assertActionVisible('downloadReport')
        ->assertActionVisible('retryFailed')
        ->assertDontSee('The row data of this import was removed');
});

test('a pruned import hides the download and the retry and says the row data was removed', function () {
    $import = finishedWithFailures(['pruned_at' => now()]);

    Livewire::test(ImportResult::class, ['import' => $import])
        ->assertActionHidden('downloadReport')
        ->assertActionHidden('retryFailed')
        ->assertSee('The row data of this import was removed after the retention period. The statistics and mappings are kept.')
        ->assertSee('88');
});

test('an importing import offers no cancel', function () {
    $import = ImportBuilder::import(ImportStatus::Importing, ['stats' => ['total_groups' => 10]]);

    Livewire::test(ViewProductImport::class, ['record' => $import->id])
        ->assertActionHidden('cancelImport')
        ->assertSee('0 of 10 products processed (0%)');
});

test('an import before importing can be cancelled from its page', function () {
    $import = ImportBuilder::import(ImportStatus::PreviewReady);

    Livewire::test(ViewProductImport::class, ['record' => $import->id])
        ->assertActionVisible('cancelImport')
        ->callAction('cancelImport');

    expect($import->fresh()->status)->toBe(ImportStatus::Cancelled);
});

/**
 * A failed image of the import's first row.
 */
function failedImage(ProductImport $import): ProductImportImage
{
    return ProductImportImage::factory()->create([
        'product_import_id' => $import->id,
        'product_import_row_id' => ProductImportRow::query()->where('product_import_id', $import->id)->value('id'),
        'product_id' => Product::factory()->create()->id,
        'url' => 'https://img.example.com/missing.png',
        'status' => ImageStatus::Failed,
        'error' => 'the server answered with status 404',
    ]);
}

test('the failed images show with their product, url, and reason, with the url correction and the retry', function () {
    $import = finishedWithFailures();
    $image = failedImage($import);

    Livewire::test(ImportResult::class, ['import' => $import])
        ->assertSee('Failed images')
        ->assertSee('#'.$image->product_id)
        ->assertSee('https://img.example.com/missing.png')
        ->assertSee('the server answered with status 404')
        ->assertActionVisible('retryImages')
        ->callAction('correctImageUrl', data: ['url' => 'https://img.example.com/rochie.png'], arguments: ['image' => $image->id])
        ->assertHasNoActionErrors();

    expect($image->fresh()->url)->toBe('https://img.example.com/rochie.png');
});

test('without failed images, or once the rows are removed, neither the failed images nor their retry show', function () {
    $import = finishedWithFailures();

    Livewire::test(ImportResult::class, ['import' => $import])
        ->assertDontSee('Failed images')
        ->assertActionHidden('retryImages');

    $pruned = finishedWithFailures(['pruned_at' => now()]);
    failedImage($pruned);

    Livewire::test(ImportResult::class, ['import' => $pruned])
        ->assertDontSee('Failed images')
        ->assertDontSee('https://img.example.com/missing.png')
        ->assertActionHidden('retryImages');
});
