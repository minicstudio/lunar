<?php

use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\ProductImport\Actions\StartImport;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Enums\RowSkipReason;
use Lunar\ProductImport\Enums\RowStatus;
use Lunar\ProductImport\Exceptions\ImportActionRefused;
use Lunar\ProductImport\Jobs\CommitAssociations;
use Lunar\ProductImport\Jobs\CommitProductGroups;
use Lunar\ProductImport\Jobs\CommitTaxonomy;
use Lunar\ProductImport\Jobs\FinalizeImport;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();

    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    $this->productType = ProductType::factory()->create();
});

/**
 * A preview-ready import of 118 one-variant products, the first `$errors` of them without a price.
 */
function previewReadyImport(int $productTypeId, int $count = 118, int $errors = 6): ProductImport
{
    $import = ImportBuilder::import(ImportStatus::Staging);

    $sheet = ImportBuilder::sheet($import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret'],
        $count,
        fn (int $i) => ["SKU-{$i}", "Produs {$i}", $i <= $errors ? '' : '49,90'],
    ));
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price'], $productTypeId);

    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::PreviewReady]);

    return $import;
}

test('the start action names the variants and skips the 6 error rows after confirmation', function () {
    $import = previewReadyImport($this->productType->id);

    expect(app(StartImport::class)->label($import))->toBe('Skip rows with errors and import (112 variants)');

    expect(fn () => app(StartImport::class)->handle($import))
        ->toThrow(ImportActionRefused::class, 'Skip rows with errors and import (112 variants)');

    Bus::assertNotDispatched(CommitTaxonomy::class);

    app(StartImport::class)->handle($import->fresh(), confirmed: true);

    expect($import->fresh()->status)->toBe(ImportStatus::Importing)
        ->and($import->fresh()->started_at)->not->toBeNull()
        ->and($import->fresh()->stats['total_groups'])->toBe(112)
        ->and(ProductImportRow::where('skip_reason', RowSkipReason::Staff)->where('status', RowStatus::Skipped)->count())->toBe(6);
});

test('an import without errors starts without confirmation', function () {
    $import = previewReadyImport($this->productType->id, 3, 0);

    expect(app(StartImport::class)->label($import))->toBe('Import (3 variants)');

    app(StartImport::class)->handle($import);

    expect($import->fresh()->status)->toBe(ImportStatus::Importing);
});

test('an import with only error rows is refused', function () {
    $import = previewReadyImport($this->productType->id, 4, 4);

    expect(fn () => app(StartImport::class)->handle($import, confirmed: true))
        ->toThrow(ImportActionRefused::class, 'No row can be imported. Correct or include rows first.');

    expect($import->fresh()->status)->toBe(ImportStatus::PreviewReady);
});

test('a second import is refused while another one is importing and stays in preview ready', function () {
    ProductImport::factory()->status(ImportStatus::Importing)->create();
    $import = previewReadyImport($this->productType->id, 3, 0);

    expect(fn () => app(StartImport::class)->handle($import))
        ->toThrow(ImportActionRefused::class, 'Another import is running. Try again when it has finished.');

    expect($import->fresh()->status)->toBe(ImportStatus::PreviewReady);
    Bus::assertNotDispatched(CommitTaxonomy::class);
});

test('the commit chain is dispatched on the default queue and connection', function () {
    config(['lunar.product-import.commit.groups_per_job' => 25]);
    $import = previewReadyImport($this->productType->id, 60, 0);

    app(StartImport::class)->handle($import);

    Bus::assertChained([
        CommitTaxonomy::class,
        Bus::chainedBatch(fn (PendingBatch $batch) => $batch->jobs->count() === 3
            && $batch->allowsFailures()
            && $batch->queue() === null
            && $batch->connection() === null
            && $batch->jobs->every(fn (CommitProductGroups $job) => $job->importId === $import->id)
            && $batch->jobs->sum(fn (CommitProductGroups $job) => count($job->groupKeys)) === 60),
        CommitAssociations::class,
        FinalizeImport::class,
    ]);

    Bus::assertDispatched(CommitTaxonomy::class, fn (CommitTaxonomy $job) => $job->importId === $import->id
        && $job->queue === null
        && $job->connection === null);
});

test('the commit chain is dispatched on the configured queue and connection when they are set', function () {
    config([
        'lunar.product-import.queue.name' => 'product-import',
        'lunar.product-import.queue.connection' => 'database',
    ]);
    $import = previewReadyImport($this->productType->id, 3, 0);

    app(StartImport::class)->handle($import);

    Bus::assertChained([
        CommitTaxonomy::class,
        Bus::chainedBatch(fn (PendingBatch $batch) => $batch->queue() === 'product-import'
            && $batch->connection() === 'database'),
        CommitAssociations::class,
        FinalizeImport::class,
    ]);

    Bus::assertDispatched(CommitTaxonomy::class, fn (CommitTaxonomy $job) => $job->importId === $import->id
        && $job->queue === 'product-import'
        && $job->connection === 'database');
});
