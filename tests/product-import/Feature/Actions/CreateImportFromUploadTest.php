<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Lunar\ProductImport\Actions\CreateImportFromUpload;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Jobs\ReadImportFile;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\Tests\ProductImport\Support\AiFakes;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    AiFakes::configure();
});

test('an upload with no provider configured is refused with the not-configured message', function () {
    config(['lunar.product-import.ai.provider' => null]);

    expect(fn () => app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->create('lista.xlsx', 100)))
        ->toThrow(ValidationException::class, 'AI analysis is not configured. The provider or API key is missing. Contact your developer.');

    expect(ProductImport::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a pdf is rejected with the allowed formats and no import is created', function () {
    expect(fn () => app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->create('lista.pdf', 100)))
        ->toThrow(ValidationException::class, 'The file "lista.pdf" must be an XLSX, XLS, or CSV file.');

    expect(ProductImport::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a 25 mb file is rejected with the size limit and no import is created', function () {
    expect(fn () => app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->create('lista.csv', 25 * 1024)))
        ->toThrow(ValidationException::class, 'The file "lista.csv" may not be larger than 20 MB.');

    expect(ProductImport::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('an xlsx creates an uploaded import and queues reading on the default queue and connection', function () {
    $staff = $this->makeStaff();

    $import = app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->create('Produse noiembrie.XLSX', 2048), $staff);

    $file = $import->files()->sole();

    expect($import->status)->toBe(ImportStatus::Uploaded)
        ->and($import->staff_id)->toBe($staff->id)
        ->and($file->original_name)->toBe('Produse noiembrie.XLSX')
        ->and($file->position)->toBe(0)
        ->and($file->disk)->toBe('local')
        ->and($file->size)->toBe(2048 * 1024)
        ->and($file->path)->toEndWith('.xlsx');

    Storage::disk('local')->assertExists($file->path);

    Queue::assertPushed(ReadImportFile::class, fn (ReadImportFile $job) => $job->importId === $import->id
        && $job->queue === null
        && $job->connection === null);
});

test('a csv stores the chosen encoding in its listed spelling', function () {
    $content = iconv('UTF-8', 'WINDOWS-1250', "Denumire;Megnevezés\nRochie roşie;Hosszú ujjú póló\n");

    $import = app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->createWithContent('lista.csv', $content), encoding: 'windows-1250');

    expect($import->files()->sole()->encoding)->toBe('Windows-1250');
    Queue::assertPushed(ReadImportFile::class);
});

test('a windows-1250 csv without an encoding is rejected and no import is created', function (?string $encoding) {
    $content = iconv('UTF-8', 'WINDOWS-1250', "Megnevezés\nHosszú ujjú póló\n");

    expect(fn () => app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->createWithContent('lista.csv', $content), encoding: $encoding))
        ->toThrow(ValidationException::class, "The CSV file \"lista.csv\" is not UTF-8. Choose the file's encoding and upload it again.");

    expect(ProductImport::count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'no encoding' => [null],
    'utf-8' => ['UTF-8'],
]);

test('a utf-8 csv is accepted with any encoding chosen', function () {
    $content = "\xEF\xBB\xBFDenumire;Megnevezés\nRochie roșie;Hosszú ujjú póló\n";

    $import = app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->createWithContent('lista.csv', $content), encoding: 'Windows-1250');

    expect($import->files()->sole()->encoding)->toBe('Windows-1250');
});

test('an unknown encoding is rejected and no import is created', function () {
    expect(fn () => app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->createWithContent('lista.csv', "Cod\nA-1\n"), encoding: 'KOI8-R'))
        ->toThrow(ValidationException::class, 'Choose one of these encodings: UTF-8, Windows-1250, Windows-1252, ISO-8859-2, ISO-8859-16.');

    expect(ProductImport::count())->toBe(0);
});

test('an encoding chosen for an xlsx file is not stored', function () {
    $import = app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->create('lista.xlsx', 100), encoding: 'Windows-1250');

    expect($import->files()->sole()->encoding)->toBeNull();
});

test('reading is queued on the configured queue and connection when they are set', function () {
    config([
        'lunar.product-import.queue.name' => 'product-import',
        'lunar.product-import.queue.connection' => 'database',
    ]);

    $import = app(CreateImportFromUpload::class)->handle(UploadedFile::fake()->create('lista.xlsx', 100));

    Queue::assertPushedOn('product-import', ReadImportFile::class, fn (ReadImportFile $job) => $job->importId === $import->id
        && $job->connection === 'database');
});

test('two csv files create one import with two file rows', function () {
    $import = app(CreateImportFromUpload::class)->handle([
        UploadedFile::fake()->createWithContent('products_import.csv', "Product ID;Name\n12;Cămașă\n"),
        UploadedFile::fake()->createWithContent('combinations_import.csv', "Product ID;Reference\n12;CAM-12-S\n"),
    ], encoding: 'Windows-1250');

    $files = $import->files()->get();

    expect(ProductImport::count())->toBe(1)
        ->and($files->pluck('original_name')->all())->toBe(['products_import.csv', 'combinations_import.csv'])
        ->and($files->pluck('position')->all())->toBe([0, 1])
        ->and($files->pluck('encoding')->all())->toBe(['Windows-1250', 'Windows-1250']);

    $files->each(fn ($file) => Storage::disk('local')->assertExists($file->path));
    Queue::assertPushed(ReadImportFile::class, 1);
});

test('a pdf among the files rejects the whole upload, naming it', function () {
    expect(fn () => app(CreateImportFromUpload::class)->handle([
        UploadedFile::fake()->createWithContent('products_import.csv', "Product ID;Name\n12;Cămașă\n"),
        UploadedFile::fake()->create('catalog.pdf', 100),
    ]))->toThrow(ValidationException::class, 'The file "catalog.pdf" must be an XLSX, XLS, or CSV file.');

    expect(ProductImport::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});
