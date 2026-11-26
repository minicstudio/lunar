<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Models\ProductImportValue;
use Lunar\ProductImport\ProductImportServiceProvider;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

function finishedImport(ImportStatus $status, int $daysAgo): ProductImport
{
    $import = ImportBuilder::import($status, [
        'path' => 'product-imports/'.uniqid().'.xlsx',
        'finished_at' => now()->subDays($daysAgo),
        'created_at' => now()->subDays($daysAgo + 1),
        'stats' => ['new_products' => 3],
    ]);

    Storage::disk('local')->put(ImportBuilder::filePath($import), 'file');

    $sheet = ImportBuilder::sheet($import, 'Produse', [['Cod', 'Pret'], ['A-1', '10'], ['A-2', '20']], 1, [
        'mapping' => ['columns' => [['index' => 0, 'header' => 'Cod', 'target' => 'variant.sku']]],
        'ai_proposal' => ['columns' => []],
    ]);
    ProductImportValue::factory()->for($import, 'import')->create();
    ProductImportImage::factory()->create(['product_import_id' => $import->id, 'product_import_row_id' => $sheet->rows()->value('id'), 'product_id' => 1]);

    return $import;
}

test('a completed import is pruned after 31 days but not after 29', function () {
    $old = finishedImport(ImportStatus::Completed, 31);
    $recent = finishedImport(ImportStatus::Completed, 29);

    $this->artisan('product-import:prune')->assertSuccessful();

    $old->refresh();

    expect($old->pruned_at)->not->toBeNull()
        ->and($old->status)->toBe(ImportStatus::Completed)
        ->and($old->stats)->toBe(['new_products' => 3])
        ->and(ProductImportRow::where('product_import_id', $old->id)->count())->toBe(0)
        ->and(ProductImportValue::where('product_import_id', $old->id)->count())->toBe(0)
        ->and(ProductImportImage::where('product_import_id', $old->id)->count())->toBe(0)
        ->and(ProductImportImage::where('product_import_id', $recent->id)->count())->toBe(1)
        ->and($old->sheets()->sole()->mapping)->toBe(['columns' => [['index' => 0, 'header' => 'Cod', 'target' => 'variant.sku']]])
        ->and($old->sheets()->sole()->ai_proposal)->toBe(['columns' => []])
        ->and(Storage::disk('local')->exists(ImportBuilder::filePath($old)))->toBeFalse();

    expect($recent->fresh()->pruned_at)->toBeNull()
        ->and(ProductImportRow::where('product_import_id', $recent->id)->count())->toBe(3)
        ->and(Storage::disk('local')->exists(ImportBuilder::filePath($recent)))->toBeTrue();
});

test('an 8-day-old import that was never started is pruned and cancelled', function () {
    $abandoned = ImportBuilder::import(ImportStatus::PreviewReady, ['path' => 'product-imports/abandoned.xlsx', 'created_at' => now()->subDays(8)]);
    Storage::disk('local')->put(ImportBuilder::filePath($abandoned), 'file');
    ImportBuilder::sheet($abandoned, 'Produse', [['Cod'], ['A-1']]);

    $fresh = ImportBuilder::import(ImportStatus::PreviewReady, ['created_at' => now()->subDays(6)]);
    ImportBuilder::sheet($fresh, 'Produse', [['Cod'], ['A-1']]);

    $this->artisan('product-import:prune')->assertSuccessful();

    expect($abandoned->fresh()->status)->toBe(ImportStatus::Cancelled)
        ->and($abandoned->fresh()->pruned_at)->not->toBeNull()
        ->and(ProductImportRow::where('product_import_id', $abandoned->id)->count())->toBe(0)
        ->and(Storage::disk('local')->exists(ImportBuilder::filePath($abandoned)))->toBeFalse()
        ->and($fresh->fresh()->status)->toBe(ImportStatus::PreviewReady)
        ->and($fresh->fresh()->pruned_at)->toBeNull();
});

test('an import that is importing is never pruned', function () {
    $import = ImportBuilder::import(ImportStatus::Importing, ['created_at' => now()->subDays(40)]);

    $this->artisan('product-import:prune')->assertSuccessful();

    expect($import->fresh()->pruned_at)->toBeNull()
        ->and($import->fresh()->status)->toBe(ImportStatus::Importing);
});

/**
 * Whether the prune command is on the schedule.
 */
function pruneIsScheduled(): bool
{
    return collect(app(Schedule::class)->events())
        ->contains(fn (Event $event) => str_contains((string) $event->command, 'product-import:prune'));
}

test('the prune command is scheduled only when the feature is enabled', function () {
    expect(config('lunar.product-import.enabled'))->toBeFalse()
        ->and(pruneIsScheduled())->toBeFalse();

    config(['lunar.product-import.enabled' => true]);
    (new ProductImportServiceProvider(app()))->registerSchedule();

    $event = collect(app(Schedule::class)->events())->first(fn (Event $event) => str_contains((string) $event->command, 'product-import:prune'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *');
});

test('every file of an import is deleted', function () {
    $import = finishedImport(ImportStatus::Completed, 31);
    $second = $import->files()->create([
        'position' => 1,
        'original_name' => 'combinations_import.csv',
        'disk' => 'local',
        'path' => 'product-imports/'.uniqid().'.csv',
    ]);
    Storage::disk('local')->put($second->path, 'file');

    $this->artisan('product-import:prune')->assertSuccessful();

    expect($import->files()->count())->toBe(2)
        ->and(Storage::disk('local')->exists(ImportBuilder::filePath($import)))->toBeFalse()
        ->and(Storage::disk('local')->exists($second->path))->toBeFalse();
});
