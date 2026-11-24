<?php

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Filament\Resources\ProductImportResource;
use Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages\ListProductImports;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportSheet;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    Filament::setCurrentPanel(Filament::getPanel('lunar'));
    AiFakes::configure();

    $this->actingAs($this->makeStaff(admin: true), 'staff');
});

test('the list shows the status and the short result of a finished import', function () {
    ProductImport::factory()->status(ImportStatus::CompletedWithErrors)->withFile('furnizor-noiembrie.xlsx')->create([
        'stats' => ['new_products' => 88, 'updated_products' => 13, 'failed_rows' => 7],
        'finished_at' => now(),
    ]);

    Livewire::test(ListProductImports::class)
        ->assertSee('furnizor-noiembrie.xlsx')
        ->assertSee('Completed with errors')
        ->assertSee('88 new')
        ->assertSee('7 errors');
});

test('an importing import shows the share of processed groups', function () {
    $import = ProductImport::factory()->status(ImportStatus::Importing)->withFile('in-curs.xlsx')->create([
        'stats' => ['total_groups' => 4],
    ]);
    $sheet = ProductImportSheet::factory()->for($import, 'import')->create(['header_row' => 1]);

    foreach (['a' => RowStatus::Imported, 'b' => RowStatus::Imported, 'c' => RowStatus::Failed, 'd' => RowStatus::Valid] as $key => $status) {
        ProductImportRow::factory()->for($sheet, 'sheet')->create(['row_number' => ord($key), 'group_key' => $key, 'status' => $status]);
    }

    Livewire::test(ListProductImports::class)->assertSee('75%');

    expect(ProductImportResource::progress($import))->toBe('75%');
});

test('the list is sorted newest first and shows the ai line', function () {
    ProductImport::factory()->withFile('vechi.xlsx')->create(['created_at' => now()->subDay()]);
    ProductImport::factory()->withFile('nou.xlsx')->create();

    Livewire::test(ListProductImports::class)
        ->assertSeeInOrder(['nou.xlsx', 'vechi.xlsx'])
        ->assertSee('AI analysis: openai · default model');
});

test('an upload creates an import and redirects to it', function () {
    Storage::fake('local');
    Queue::fake();

    Livewire::test(ListProductImports::class)
        ->callAction('newImport', data: ['file' => UploadedFile::fake()->create('produse.xlsx', 100)])
        ->assertHasNoActionErrors()
        ->assertRedirect(ProductImportResource::getUrl('view', ['record' => ProductImport::query()->sole()]));

    expect(ProductImport::query()->sole()->files()->sole()->original_name)->toBe('produse.xlsx');
});

test('several files become one import, listed by the first file and the count of the others', function () {
    Storage::fake('local');
    Queue::fake();

    Livewire::test(ListProductImports::class)
        ->callAction('newImport', data: ['file' => [
            UploadedFile::fake()->createWithContent('products_import.csv', "Product ID;Name\n12;Cămașă\n"),
            UploadedFile::fake()->createWithContent('combinations_import.csv', "Product ID;Reference\n12;CAM-12-S\n"),
        ]])
        ->assertHasNoActionErrors();

    $import = ProductImport::query()->sole();

    expect($import->files()->pluck('original_name')->all())->toBe(['products_import.csv', 'combinations_import.csv'])
        ->and($import->fileLabel())->toBe('products_import.csv (+1)');

    Livewire::test(ListProductImports::class)->assertSee('products_import.csv (+1)');
});

test('the upload form offers the csv encodings and stores the chosen one', function () {
    Storage::fake('local');
    Queue::fake();

    $content = iconv('UTF-8', 'WINDOWS-1250', "Cod;Megnevezés\nA-1;Hosszú ujjú póló\n");

    Livewire::test(ListProductImports::class)
        ->mountAction('newImport')
        ->assertFormFieldExists('encoding', 'mountedActionSchema0', fn (Select $field): bool => $field->getLabel() === 'Encoding (CSV files)'
            && array_keys($field->getOptions()) === ['UTF-8', 'Windows-1250', 'Windows-1252', 'ISO-8859-2', 'ISO-8859-16'])
        ->assertActionDataSet(['encoding' => 'UTF-8'])
        ->setActionData(['file' => UploadedFile::fake()->createWithContent('produse.csv', $content), 'encoding' => 'Windows-1250'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(ProductImport::query()->sole()->files()->sole()->encoding)->toBe('Windows-1250');
});

test('the upload is disabled while the ai is not configured', function () {
    config(['lunar.product-import.ai.provider' => null]);

    Livewire::test(ListProductImports::class)
        ->assertActionDisabled('newImport')
        ->assertSee('AI analysis is not configured. The provider or API key is missing. Contact your developer.');
});
