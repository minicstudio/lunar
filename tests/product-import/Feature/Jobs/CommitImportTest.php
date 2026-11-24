<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\ProductImport\Actions\StartImport;
use Lunar\ProductImport\Enums\ImageStatus;
use Lunar\ProductImport\Enums\ImportStatus;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportImage;
use Lunar\ProductImport\Models\ProductImportRow;
use Lunar\ProductImport\Persisting\CommitContext;
use Lunar\ProductImport\Persisting\FieldValues;
use Lunar\ProductImport\Persisting\HostResolver;
use Lunar\ProductImport\Persisting\ImportResult;
use Lunar\ProductImport\Persisting\ProductUpserter;
use Lunar\ProductImport\Persisting\ProductUrls;
use Lunar\Tests\ProductImport\Support\ImportBuilder;
use Lunar\Tests\ProductImport\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create(['code' => 'ro', 'default' => true]);
    Currency::factory()->create(['code' => 'RON', 'default' => true, 'decimal_places' => 2]);
    TaxClass::factory()->create(['default' => true]);
    $this->productType = ProductType::factory()->create();
});

/**
 * Stage products of three variants (sizes S, M, L) and bring the import to preview ready.
 *
 * @param  list<string>  $parents
 */
function threeVariantImport(int $productTypeId, array $parents): ProductImport
{
    $rows = [['Cod', 'Cod parinte', 'Denumire', 'Pret', 'Stoc', 'Marime']];

    foreach ($parents as $parent) {
        foreach (['S', 'M', 'L'] as $size) {
            $rows[] = ["{$parent}-{$size}", $parent, "Produs {$parent}", '49,90', '5', $size];
        }
    }

    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', $rows);
    ImportBuilder::map($sheet, [
        0 => 'variant.sku', 1 => 'group_key', 2 => ['product.name', 'ro'], 3 => 'variant.price', 4 => 'variant.stock', 5 => 'variant.option',
    ], $productTypeId, 1);

    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::PreviewReady]);

    return $import;
}

/**
 * Stage `$count` one-variant products and bring the import to preview ready.
 */
function oneVariantImport(int $productTypeId, int $count): ProductImport
{
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret'],
        $count,
        fn (int $i) => ["SKU-{$i}", "Produs {$i}", '10'],
    ));
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price'], $productTypeId);

    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::PreviewReady]);

    return $import;
}

/**
 * Make writing fail for the products whose SKU is in the list.
 *
 * @param  list<string>  $skus
 */
function failWritingFor(array $skus): void
{
    app()->bind(ProductUpserter::class, fn () => new class(app(FieldValues::class), app(ProductUrls::class), $skus) extends ProductUpserter
    {
        public function __construct(FieldValues $fields, ProductUrls $urls, protected array $failing)
        {
            parent::__construct($fields, $urls);
        }

        public function upsert(ProductImport $import, string $groupKey, CommitContext $context): void
        {
            foreach ($this->failing as $sku) {
                if (str_ends_with($groupKey, ':row:'.((int) substr($sku, 4) + 1))) {
                    throw new RuntimeException('Lock wait timeout exceeded');
                }
            }

            parent::upsert($import, $groupKey, $context);
        }
    });
}

test('the same file imported twice leaves the variant count unchanged', function () {
    $first = threeVariantImport($this->productType->id, ['TB-1', 'TB-2']);
    app(StartImport::class)->handle($first);

    expect($first->fresh()->status)->toBe(ImportStatus::Completed)
        ->and($first->fresh()->stats)->toMatchArray([
            'new_products' => 2, 'new_variants' => 6, 'updated_products' => 0, 'failed_rows' => 0,
            'processed_groups' => 2, 'total_groups' => 2, 'created_option_values' => 3,
        ])
        ->and(ProductVariant::count())->toBe(6);

    $second = threeVariantImport($this->productType->id, ['TB-1', 'TB-2']);
    app(StartImport::class)->handle($second);

    expect($second->fresh()->status)->toBe(ImportStatus::Completed)
        ->and($second->fresh()->stats)->toMatchArray(['new_products' => 0, 'updated_products' => 2, 'updated_variants' => 6])
        ->and($second->fresh()->finished_at)->not->toBeNull()
        ->and(ProductVariant::count())->toBe(6)
        ->and(Product::count())->toBe(2);
});

test('a product that is not in the file is unchanged', function () {
    $other = Product::factory()->create(['status' => 'published']);
    $otherVariant = ProductVariant::factory()->for($other, 'product')->create(['sku' => 'OTHER-1', 'stock' => 7]);

    app(StartImport::class)->handle(threeVariantImport($this->productType->id, ['TB-1']));

    expect($other->fresh()->status)->toBe('published')
        ->and($other->fresh()->deleted_at)->toBeNull()
        ->and($otherVariant->fresh()->stock)->toBe(7);
});

test('112 imported and 7 failed rows give completed with errors', function () {
    $import = oneVariantImport($this->productType->id, 119);

    failWritingFor(['SKU-3', 'SKU-17', 'SKU-40', 'SKU-41', 'SKU-77', 'SKU-100', 'SKU-119']);

    app(StartImport::class)->handle($import);

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and($import->stats['imported_rows'])->toBe(112)
        ->and($import->stats['new_variants'])->toBe(112)
        ->and($import->stats['failed_rows'])->toBe(7)
        ->and($import->stats['processed_groups'])->toBe(119)
        ->and(Product::count())->toBe(112);
});

test('no imported row gives failed', function () {
    $import = oneVariantImport($this->productType->id, 2);

    failWritingFor(['SKU-1', 'SKU-2']);

    app(StartImport::class)->handle($import);

    expect($import->fresh()->status)->toBe(ImportStatus::Failed)
        ->and($import->fresh()->failure_message)->toBe('No row was imported.')
        ->and($import->fresh()->stats['failed_rows'])->toBe(2);
});

test('the touched products are made searchable once', function () {
    $engine = new class extends NullEngine
    {
        /** @var list<list<int>> */
        public array $updates = [];

        public function update($models)
        {
            $this->updates[] = $models->map(fn ($model) => $model::class.':'.$model->getKey())->all();
        }
    };

    $manager = new EngineManager(app());
    $manager->extend('import-spy', fn () => $engine);
    app()->instance(EngineManager::class, $manager);
    Config::set('scout.driver', 'import-spy');
    Config::set('scout.queue', false);

    app(StartImport::class)->handle(threeVariantImport($this->productType->id, ['TB-1', 'TB-2']));

    $productUpdates = array_values(array_filter(
        $engine->updates,
        fn (array $models) => str_starts_with($models[0] ?? '', Product::modelClass().':'),
    ));

    expect($productUpdates)->toHaveCount(1)
        ->and($productUpdates[0])->toHaveCount(2);
});

test('klaviyo queues one product sync for a three-variant product', function () {
    Queue::fake([SyncProductToKlaviyo::class]);

    Config::set('lunar.klaviyo.enabled', true);
    Config::set('lunar.klaviyo.api_key', 'pk_test');
    Config::set('lunar.klaviyo.sync_products', true);
    Config::set('lunar.klaviyo.queue_connection', 'deferred');

    app(StartImport::class)->handle(threeVariantImport($this->productType->id, ['TB-1']));

    $product = Product::query()->sole();

    Queue::assertPushed(SyncProductToKlaviyo::class, 1);
    Queue::assertPushed(SyncProductToKlaviyo::class, fn (SyncProductToKlaviyo $job) => $job->productId === $product->id);
});

/**
 * Stage one-variant products with an image each, the last image missing,
 * and bring the import to preview ready.
 */
function imageImport(int $productTypeId, int $count): ProductImport
{
    $import = ImportBuilder::import(ImportStatus::Staging);
    $sheet = ImportBuilder::sheet($import, 'Produse', ImportBuilder::rows(
        ['Cod', 'Denumire', 'Pret', 'Imagini'],
        $count,
        fn (int $i) => ["SKU-{$i}", "Produs {$i}", '10', $i === $count ? 'https://img.example.com/missing.png' : "https://img.example.com/{$i}.png"],
    ));
    ImportBuilder::map($sheet, [0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'product.images'], $productTypeId);

    ImportBuilder::stage($import);
    $import->update(['status' => ImportStatus::PreviewReady]);

    return $import;
}

/**
 * Serve a small PNG for every image URL but the missing one, from a public host.
 */
function fakeImageHost(): void
{
    Storage::fake('public');
    Config::set('media-library.disk_name', 'public');

    app()->instance(HostResolver::class, new class extends HostResolver
    {
        public function addresses(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    $image = imagecreatetruecolor(2, 2);
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();

    Http::fake([
        'https://img.example.com/missing.png' => Http::response('', 404),
        'https://img.example.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
    ]);
}

test('the products, then the associations, then the images are written before the result, with progress over groups and then images', function () {
    Config::set('lunar.product-import.commit.groups_per_job', 2);
    Config::set('lunar.product-import.images.per_job', 2);
    fakeImageHost();

    $import = imageImport($this->productType->id, 4);
    $order = [];
    $progress = [];

    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$order, &$progress, $import): void {
        $name = class_basename($event->job->resolveName());

        if (in_array($name, ['CommitTaxonomy', 'CommitProductGroups', 'CommitAssociations', 'DownloadImages', 'FinalizeImport'], true)) {
            $order[] = $name;
            $progress[$name] ??= app(ImportResult::class)->progress($import->fresh());
        }
    });

    app(StartImport::class)->handle($import);

    expect($order)->toBe(['CommitTaxonomy', 'CommitProductGroups', 'CommitProductGroups', 'CommitAssociations', 'DownloadImages', 'DownloadImages', 'FinalizeImport'])
        ->and($progress['CommitProductGroups'])->toBe(['phase' => 'groups', 'processed' => 0, 'total' => 4, 'percent' => 0])
        ->and($progress['DownloadImages'])->toBe(['phase' => 'images', 'processed' => 0, 'total' => 4, 'percent' => 0])
        ->and($progress['FinalizeImport'])->toBe(['phase' => 'images', 'processed' => 4, 'total' => 4, 'percent' => 100]);
});

test('a failed image leaves the import completed and is counted', function () {
    fakeImageHost();

    $import = imageImport($this->productType->id, 3);

    app(StartImport::class)->handle($import);

    $import->refresh();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->stats)->toMatchArray(['imported_rows' => 3, 'failed_rows' => 0, 'images_downloaded' => 2, 'images_failed' => 1, 'processed_images' => 3, 'total_images' => 3])
        ->and(collect(ProductImportRow::includedIn($import)->where('row_number', 4)->sole()->messages)->pluck('code')->all())->toContain('image_failed')
        ->and(ProductImportImage::query()->where('status', ImageStatus::Failed)->sole()->url)->toBe('https://img.example.com/missing.png');
});
