<?php

uses(\Lunar\Tests\Core\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Enums\ProductEventType;
use Lunar\Events\ProductDeletedEvent;
use Lunar\Events\ProductPublished;
use Lunar\Events\ProductUpdatedEvent;
use Lunar\Events\ProductVariantCreatedEvent;
use Lunar\Events\ProductVariantDeletedEvent;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Klaviyo\Jobs\DeleteAllProductsFromKlaviyo;
use Lunar\Klaviyo\Jobs\DeleteCatalogVariantFromKlaviyo;
use Lunar\Klaviyo\Jobs\SyncAllProductsToKlaviyo;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Jobs\SyncProductsBulkToKlaviyo;
use Lunar\Klaviyo\Listeners\SyncProductOnCollectionsUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnDeleted;
use Lunar\Klaviyo\Listeners\SyncProductOnPublished;
use Lunar\Klaviyo\Listeners\SyncProductOnUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnVariantCreated;
use Lunar\Klaviyo\Listeners\SyncProductOnVariantDeleted;
use Lunar\Klaviyo\Requests\BulkCreateCatalogItemsRequest;
use Lunar\Klaviyo\Requests\BulkCreateCatalogVariantsRequest;
use Lunar\Klaviyo\Requests\BulkDeleteCatalogItemsRequest;
use Lunar\Klaviyo\Requests\BulkUpdateCatalogItemsRequest;
use Lunar\Klaviyo\Requests\BulkUpdateCatalogVariantsRequest;
use Lunar\Klaviyo\Requests\CreateCatalogCategoryRequest;
use Lunar\Klaviyo\Requests\CreateEventRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogItemRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogVariantRequest;
use Lunar\Klaviyo\Requests\GetBulkCreateCatalogItemsJobRequest;
use Lunar\Klaviyo\Requests\GetCatalogItemsRequest;
use Lunar\Klaviyo\Requests\GetCatalogItemVariantIdsRequest;
use Lunar\Klaviyo\Requests\UpdateCatalogItemRequest;
use Lunar\Klaviyo\Requests\UpdateCatalogVariantRequest;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Services\KlaviyoOrderService;
use Lunar\Klaviyo\Services\KlaviyoProfileService;
use Lunar\Klaviyo\Services\KlaviyoService;
use Lunar\Klaviyo\Support\CatalogExternalIdStore;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Language::factory()->create(['default' => true, 'code' => 'en']);
    Currency::factory()->create(['default' => true, 'code' => 'EUR']);

    Queue::fake();

    Config::set('lunar.klaviyo.enabled', true);
    Config::set('lunar.klaviyo.api_key', 'pk_test');
    Config::set('lunar.klaviyo.api_revision', '2026-01-15');
    Config::set('lunar.klaviyo.sync_products', true);
    Config::set('lunar.klaviyo.catalog.default_category_external_id', 'uncategorized');
    Config::set('lunar.klaviyo.retry.max_attempts', 4);
    Config::set('lunar.klaviyo.retry.backoff', [60, 300, 3600]);
    Config::set('lunar.klaviyo.queue_connection', 'deferred');
    Config::set('app.url', 'https://shop.test');
});

test('published listener dispatches SyncProductToKlaviyo with CREATE on deferred', function () {
    $product = Product::factory()->create(['status' => 'published']);

    (new SyncProductOnPublished)->handle(new ProductPublished($product));

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::CREATE
            && $job->connection === 'deferred';
    });
});

test('published listener no-ops when sync_products disabled', function () {
    Config::set('lunar.klaviyo.sync_products', false);

    $product = Product::factory()->create(['status' => 'published']);

    (new SyncProductOnPublished)->handle(new ProductPublished($product));

    Queue::assertNotPushed(SyncProductToKlaviyo::class);
});

test('updated listener dispatches SyncProductToKlaviyo with UPDATE', function () {
    $product = Product::factory()->create(['status' => 'published']);

    (new SyncProductOnUpdated)->handle(new ProductUpdatedEvent($product));

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::UPDATE;
    });
});

test('deleted listener dispatches SyncProductToKlaviyo with DELETE using captured external ids', function () {
    $product = Product::factory()->create();
    CatalogExternalIdStore::remember($product->id, 'TEE-1');

    (new SyncProductOnDeleted)->handle(new ProductDeletedEvent($product));

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::DELETE
            && $job->itemExternalId === 'TEE-1'
            && $job->uniqueId() === 'klaviyo-product-sync-'.$product->id.'-delete';
    });
});

test('variant created listener dispatches parent UPDATE sync', function () {
    $product = Product::factory()->create(['status' => 'published']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'TEE-9']);

    (new SyncProductOnVariantCreated)->handle(new ProductVariantCreatedEvent($variant));

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::UPDATE;
    });

    expect(CatalogExternalIdStore::get($product->id))->toBe('TEE-9');
});

test('variant deleted listener dispatches DeleteCatalogVariantFromKlaviyo and captures SKU', function () {
    $product = Product::factory()->create(['status' => 'published']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'TEE-DEL']);

    (new SyncProductOnVariantDeleted)->handle(new ProductVariantDeletedEvent($variant));

    Queue::assertPushed(DeleteCatalogVariantFromKlaviyo::class, function (DeleteCatalogVariantFromKlaviyo $job) use ($product) {
        return $job->variantExternalId === 'TEE-DEL'
            && $job->productId === $product->id;
    });

    expect(CatalogExternalIdStore::get($product->id))->toBe('TEE-DEL');
});

test('collections updated listener dispatches SyncProductToKlaviyo with UPDATE', function () {
    $product = Product::factory()->create(['status' => 'published']);
    $event = new \Lunar\Admin\Events\ProductCollectionsUpdated($product);

    (new SyncProductOnCollectionsUpdated)->handle($event);

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::UPDATE;
    });
});

test('collections updated listener no-ops when sync_products disabled', function () {
    Config::set('lunar.klaviyo.sync_products', false);

    $product = Product::factory()->create(['status' => 'published']);
    $event = new \Lunar\Admin\Events\ProductCollectionsUpdated($product);

    (new SyncProductOnCollectionsUpdated)->handle($event);

    Queue::assertNotPushed(SyncProductToKlaviyo::class);
});

test('variant options updated listener dispatches SyncProductToKlaviyo with UPDATE', function () {
    $product = Product::factory()->create(['status' => 'published']);
    $event = new \Lunar\Admin\Events\ProductVariantOptionsUpdated($product);

    (new \Lunar\Klaviyo\Listeners\SyncProductOnVariantOptionsUpdated)->handle($event);

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::UPDATE;
    });
});

test('variant pricing updated listener dispatches SyncProductToKlaviyo with UPDATE', function () {
    $product = Product::factory()->create(['status' => 'published']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'TEE-PRICE']);
    $event = new \Lunar\Admin\Events\ProductVariantPricingUpdated($variant);

    (new \Lunar\Klaviyo\Listeners\SyncProductOnVariantPricingUpdated)->handle($event);

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::UPDATE;
    });
});

test('variant options updated listener no-ops when sync_products disabled', function () {
    Config::set('lunar.klaviyo.sync_products', false);

    $product = Product::factory()->create(['status' => 'published']);
    $event = new \Lunar\Admin\Events\ProductVariantOptionsUpdated($product);

    (new \Lunar\Klaviyo\Listeners\SyncProductOnVariantOptionsUpdated)->handle($event);

    Queue::assertNotPushed(SyncProductToKlaviyo::class);
});

test('media updated listener dispatches SyncProductToKlaviyo with UPDATE', function () {
    $product = Product::factory()->create(['status' => 'published']);
    $event = new \Lunar\Admin\Events\ModelMediaUpdated($product);

    (new \Lunar\Klaviyo\Listeners\SyncProductOnMediaUpdated)->handle($event);

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::UPDATE;
    });
});

test('media updated listener no-ops for non-product models', function () {
    $collection = \Lunar\Models\Collection::factory()->create();
    $event = new \Lunar\Admin\Events\ModelMediaUpdated($collection);

    (new \Lunar\Klaviyo\Listeners\SyncProductOnMediaUpdated)->handle($event);

    Queue::assertNotPushed(SyncProductToKlaviyo::class);
});

test('media updated listener no-ops when sync_products disabled', function () {
    Config::set('lunar.klaviyo.sync_products', false);

    $product = Product::factory()->create(['status' => 'published']);
    $event = new \Lunar\Admin\Events\ModelMediaUpdated($product);

    (new \Lunar\Klaviyo\Listeners\SyncProductOnMediaUpdated)->handle($event);

    Queue::assertNotPushed(SyncProductToKlaviyo::class);
});

test('urls updated listener dispatches SyncProductToKlaviyo with UPDATE', function () {
    $product = Product::factory()->create(['status' => 'published']);
    $event = new \Lunar\Admin\Events\ModelUrlsUpdated($product);

    (new \Lunar\Klaviyo\Listeners\SyncProductOnUrlsUpdated)->handle($event);

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::UPDATE;
    });
});

test('urls updated listener no-ops for non-product models', function () {
    $collection = \Lunar\Models\Collection::factory()->create();
    $event = new \Lunar\Admin\Events\ModelUrlsUpdated($collection);

    (new \Lunar\Klaviyo\Listeners\SyncProductOnUrlsUpdated)->handle($event);

    Queue::assertNotPushed(SyncProductToKlaviyo::class);
});

test('urls updated listener no-ops when sync_products disabled', function () {
    Config::set('lunar.klaviyo.sync_products', false);

    $product = Product::factory()->create(['status' => 'published']);
    $event = new \Lunar\Admin\Events\ModelUrlsUpdated($product);

    (new \Lunar\Klaviyo\Listeners\SyncProductOnUrlsUpdated)->handle($event);

    Queue::assertNotPushed(SyncProductToKlaviyo::class);
});

test('catalog jobs implement ShouldQueueAfterCommit so admin transactional variant deletes stay consistent', function () {
    $product = Product::factory()->create();
    $upsert = SyncProductToKlaviyo::fromProduct($product, ProductEventType::UPDATE);
    $delete = new DeleteCatalogVariantFromKlaviyo('99', $product->id);

    expect($upsert)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueueAfterCommit::class)
        ->and($delete)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueueAfterCommit::class);
});

test('SyncProductToKlaviyo unique ids distinguish upsert vs delete', function () {
    $product = Product::factory()->create();

    $upsert = SyncProductToKlaviyo::fromProduct($product, ProductEventType::UPDATE);
    $delete = SyncProductToKlaviyo::fromProduct($product, ProductEventType::DELETE);

    expect($upsert->uniqueId())->toBe('klaviyo-product-sync-'.$product->id)
        ->and($delete->uniqueId())->toBe('klaviyo-product-sync-'.$product->id.'-delete')
        ->and($upsert->uniqueFor)->toBe(7200)
        ->and($upsert->tries)->toBe(4)
        ->and($upsert->backoff)->toBe([60, 300, 3600]);
});

test('SyncProductToKlaviyo calls syncProduct for CREATE and UPDATE', function () {
    $product = Product::factory()->create(['status' => 'published']);

    $mock = Mockery::mock(KlaviyoCatalogService::class);
    $mock->shouldReceive('syncProduct')->once()->with(Mockery::on(fn ($p) => $p->is($product)))->andReturn([]);
    app()->instance(KlaviyoCatalogService::class, $mock);

    SyncProductToKlaviyo::fromProduct($product, ProductEventType::CREATE)->handle();
});

test('SyncProductToKlaviyo DELETE uses deleteProductByExternalIds without restoring model', function () {
    $mock = Mockery::mock(KlaviyoCatalogService::class);
    $mock->shouldReceive('captureExternalIdsForProductId')
        ->once()
        ->with(99, 'TEE-1', ['99'])
        ->andReturn(['TEE-1', '99']);
    $mock->shouldReceive('deleteProductByExternalIds')
        ->once()
        ->with(['TEE-1', '99'])
        ->andReturn(true);
    app()->instance(KlaviyoCatalogService::class, $mock);

    (new SyncProductToKlaviyo(
        productId: 99,
        eventType: ProductEventType::DELETE,
        itemExternalId: 'TEE-1',
        additionalExternalIds: ['99'],
    ))->handle();
});

test('SyncProductToKlaviyo no-ops when disabled', function () {
    Config::set('lunar.klaviyo.enabled', false);

    $product = Product::factory()->create();

    $mock = Mockery::mock(KlaviyoCatalogService::class);
    $mock->shouldNotReceive('syncProduct');
    $mock->shouldNotReceive('deleteProductByExternalIds');
    app()->instance(KlaviyoCatalogService::class, $mock);

    SyncProductToKlaviyo::fromProduct($product)->handle();
});

test('catalog service deletes unpublished products by captured external ids', function () {
    $product = Product::factory()->create(['status' => 'draft']);
    ProductVariant::factory()->for($product)->create(['sku' => 'DRAFT-1']);

    $mockClient = new MockClient([
        DeleteCatalogItemRequest::class => MockResponse::make([], 204),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    $result = (new KlaviyoCatalogService($klaviyo))->syncProduct($product->fresh(['variants']));

    expect($result)->toBeArray()->toBeEmpty();

    $mockClient->assertSent(DeleteCatalogItemRequest::class);
});

test('catalog service availability exceptions do not delete remotely', function () {
    $product = Product::factory()->create(['status' => 'published']);

    $mockClient = new MockClient([
        DeleteCatalogItemRequest::class => MockResponse::make([], 204),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    $catalog = Mockery::mock(KlaviyoCatalogService::class, [$klaviyo])->makePartial();
    $catalog->shouldAllowMockingProtectedMethods();
    $catalog->shouldReceive('ensureCatalogStorefrontContext')->once()->andReturnNull();

    $productMock = Mockery::mock($product)->makePartial();
    $productMock->shouldReceive('isAvailable')->once()->andThrow(new RuntimeException('availability boom'));

    expect(fn () => $catalog->syncProduct($productMock))
        ->toThrow(RuntimeException::class, 'availability boom');

    $mockClient->assertNotSent(DeleteCatalogItemRequest::class);
});

test('catalog service deleteProductByExternalIds treats 404 as success', function () {
    $mockClient = new MockClient([
        DeleteCatalogItemRequest::class => MockResponse::make([], 404),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    expect((new KlaviyoCatalogService($klaviyo))->deleteProductByExternalIds(['TEE-1', '42']))->toBeTrue();
});

test('catalog service upserts item and variants with distinct external ids', function () {
    $currency = Currency::where('default', true)->first();
    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    \Lunar\Facades\StorefrontSession::setChannel($channel);
    \Lunar\Facades\StorefrontSession::setCustomerGroups(collect([$customerGroup]));

    $product = Product::factory()->create([
        'status' => 'published',
        'attribute_data' => [
            'name' => new TranslatedText(collect(['en' => 'Catalog Tee'])),
            'description' => new TranslatedText(collect(['en' => 'A nice tee'])),
        ],
    ]);

    $product->scheduleChannel($channel, now()->subDay());
    $product->scheduleCustomerGroup($customerGroup);

    $variant = ProductVariant::factory()->for($product)->create([
        'sku' => 'TEE-1',
        'stock' => 5,
    ]);

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 1999,
    ]);

    $mockClient = new MockClient([
        CreateCatalogCategoryRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::uncategorized'],
        ], 201),
        BulkCreateCatalogItemsRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-item-bulk-create-job',
                'id' => 'item-job-1',
                'attributes' => ['status' => 'queued'],
            ],
        ], 202),
        GetBulkCreateCatalogItemsJobRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-item-bulk-create-job',
                'id' => 'item-job-1',
                'attributes' => [
                    'status' => 'complete',
                    'failed_count' => 0,
                    'completed_count' => 1,
                    'total_count' => 1,
                    'errors' => [],
                ],
            ],
        ], 200),
        BulkCreateCatalogVariantsRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-variant-bulk-create-job',
                'id' => 'variant-job-1',
                'attributes' => ['status' => 'queued'],
            ],
        ], 202),
        GetCatalogItemVariantIdsRequest::class => MockResponse::make([
            'errors' => [
                [
                    'status' => 404,
                    'code' => 'not_found',
                    'title' => 'Not found.',
                ],
            ],
        ], 404),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    expect($product->fresh()->isAvailable())->toBeTrue();

    $result = (new KlaviyoCatalogService($klaviyo))->syncProduct(
        $product->fresh(['variants', 'collections', 'brand', 'media'])
    );

    expect($result)->not->toBeEmpty()
        ->and(CatalogExternalIdStore::get($product->id))->toBe('TEE-1');

    $mockClient->assertSent(BulkCreateCatalogItemsRequest::class);
    $mockClient->assertSent(BulkCreateCatalogVariantsRequest::class);

    $mockClient->assertSent(function ($request) {
        return $request instanceof BulkCreateCatalogItemsRequest
            && ($request->body()->all()['data']['attributes']['items']['data'][0]['attributes']['external_id'] ?? null) === 'TEE-1';
    });

    $mockClient->assertSent(function ($request) {
        if (! $request instanceof BulkCreateCatalogVariantsRequest) {
            return false;
        }

        $body = $request->body()->all();
        $variantResource = $body['data']['attributes']['variants']['data'][0] ?? [];

        return ($variantResource['attributes']['external_id'] ?? null) === 'TEE-1'
            && ($variantResource['relationships']['item']['data']['id'] ?? null) === '$custom:::$default:::TEE-1';
    });
});

test('catalog service patches single catalog item on update and omits item relationship on variant resources', function () {
    $currency = Currency::where('default', true)->first();
    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    \Lunar\Facades\StorefrontSession::setChannel($channel);
    \Lunar\Facades\StorefrontSession::setCustomerGroups(collect([$customerGroup]));

    $product = Product::factory()->create([
        'status' => 'published',
        'attribute_data' => [
            'name' => new TranslatedText(collect(['en' => 'Catalog Tee'])),
            'description' => new TranslatedText(collect(['en' => 'A nice tee'])),
        ],
    ]);

    $product->scheduleChannel($channel, now()->subDay());
    $product->scheduleCustomerGroup($customerGroup);

    $variant = ProductVariant::factory()->for($product)->create([
        'sku' => 'TEE-1',
        'stock' => 5,
    ]);

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 1999,
    ]);

    CatalogExternalIdStore::remember($product->id, 'TEE-1');

    $mockClient = new MockClient([
        CreateCatalogCategoryRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::uncategorized'],
        ], 201),
        UpdateCatalogItemRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-item',
                'id' => '$custom:::$default:::TEE-1',
                'attributes' => [
                    'title' => 'Catalog Tee',
                    'description' => 'A nice tee',
                ],
            ],
        ], 200),
        UpdateCatalogVariantRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-variant',
                'id' => '$custom:::$default:::TEE-1',
            ],
        ], 200),
        GetCatalogItemVariantIdsRequest::class => MockResponse::make([
            'data' => [
                ['type' => 'catalog-variant', 'id' => '$custom:::$default:::TEE-1'],
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoCatalogService($klaviyo))->syncProduct(
        $product->fresh(['variants', 'collections', 'brand', 'media'])
    );

    $mockClient->assertSent(UpdateCatalogItemRequest::class);
    $mockClient->assertSent(UpdateCatalogVariantRequest::class);
    $mockClient->assertNotSent(BulkCreateCatalogItemsRequest::class);
    $mockClient->assertNotSent(BulkCreateCatalogVariantsRequest::class);
    $mockClient->assertNotSent(BulkUpdateCatalogItemsRequest::class);
    $mockClient->assertNotSent(BulkUpdateCatalogVariantsRequest::class);

    $mockClient->assertSent(function ($request) {
        if (! $request instanceof UpdateCatalogItemRequest) {
            return false;
        }

        $body = $request->body()->all();
        $itemResource = $body['data'] ?? [];

        return ($itemResource['id'] ?? null) === '$custom:::$default:::TEE-1'
            && ($itemResource['attributes']['title'] ?? null) === 'Catalog Tee'
            && ($itemResource['attributes']['description'] ?? null) === 'A nice tee'
            && array_key_exists('categories', $itemResource['relationships'] ?? []);
    });

    $mockClient->assertSent(function ($request) {
        if (! $request instanceof UpdateCatalogVariantRequest) {
            return false;
        }

        $body = $request->body()->all();
        $variantResource = $body['data'] ?? [];

        return ($variantResource['id'] ?? null) === '$custom:::$default:::TEE-1'
            && ($variantResource['attributes']['title'] ?? null) === 'Catalog Tee'
            && ! array_key_exists('relationships', $variantResource);
    });
});

test('catalog service patches item when remote item exists even without store entry', function () {
    $currency = Currency::where('default', true)->first();
    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    \Lunar\Facades\StorefrontSession::setChannel($channel);
    \Lunar\Facades\StorefrontSession::setCustomerGroups(collect([$customerGroup]));

    $product = Product::factory()->create([
        'status' => 'published',
        'attribute_data' => [
            'name' => new TranslatedText(collect(['en' => 'Catalog Tee'])),
            'description' => new TranslatedText(collect(['en' => 'A nice tee'])),
        ],
    ]);

    $product->scheduleChannel($channel, now()->subDay());
    $product->scheduleCustomerGroup($customerGroup);

    $variant = ProductVariant::factory()->for($product)->create([
        'sku' => 'TEE-1',
        'stock' => 5,
    ]);

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 1999,
    ]);

    $mockClient = new MockClient([
        CreateCatalogCategoryRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::uncategorized'],
        ], 201),
        UpdateCatalogItemRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-item',
                'id' => '$custom:::$default:::TEE-1',
            ],
        ], 200),
        UpdateCatalogVariantRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-variant',
                'id' => '$custom:::$default:::TEE-1',
            ],
        ], 200),
        GetCatalogItemVariantIdsRequest::class => MockResponse::make([
            'data' => [
                ['type' => 'catalog-variant', 'id' => '$custom:::$default:::TEE-1'],
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoCatalogService($klaviyo))->syncProduct(
        $product->fresh(['variants', 'collections', 'brand', 'media'])
    );

    $mockClient->assertSent(UpdateCatalogItemRequest::class);
    $mockClient->assertNotSent(BulkCreateCatalogItemsRequest::class);
    expect(CatalogExternalIdStore::get($product->id))->toBe('TEE-1');
});

test('catalog service uses bulk create when store entry exists but remote item is absent', function () {
    $currency = Currency::where('default', true)->first();
    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    \Lunar\Facades\StorefrontSession::setChannel($channel);
    \Lunar\Facades\StorefrontSession::setCustomerGroups(collect([$customerGroup]));

    $product = Product::factory()->create([
        'status' => 'published',
        'attribute_data' => [
            'name' => new TranslatedText(collect(['en' => 'Catalog Tee'])),
            'description' => new TranslatedText(collect(['en' => 'A nice tee'])),
        ],
    ]);

    $product->scheduleChannel($channel, now()->subDay());
    $product->scheduleCustomerGroup($customerGroup);

    $variant = ProductVariant::factory()->for($product)->create([
        'sku' => 'TEE-1',
        'stock' => 5,
    ]);

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 1999,
    ]);

    CatalogExternalIdStore::remember($product->id, 'TEE-1');

    $mockClient = new MockClient([
        CreateCatalogCategoryRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::uncategorized'],
        ], 201),
        BulkCreateCatalogItemsRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-item-bulk-create-job',
                'id' => 'item-create-job-1',
            ],
        ], 202),
        GetBulkCreateCatalogItemsJobRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-item-bulk-create-job',
                'id' => 'item-create-job-1',
                'attributes' => [
                    'status' => 'complete',
                    'failed_count' => 0,
                    'errors' => [],
                ],
            ],
        ], 200),
        BulkCreateCatalogVariantsRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-variant-bulk-create-job',
                'id' => 'variant-create-job-1',
            ],
        ], 202),
        GetCatalogItemVariantIdsRequest::class => MockResponse::make([
            'errors' => [
                [
                    'status' => 404,
                    'code' => 'not_found',
                    'title' => 'Not found.',
                ],
            ],
        ], 404),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoCatalogService($klaviyo))->syncProduct(
        $product->fresh(['variants', 'collections', 'brand', 'media'])
    );

    $mockClient->assertSent(BulkCreateCatalogItemsRequest::class);
    $mockClient->assertSent(GetBulkCreateCatalogItemsJobRequest::class);
    $mockClient->assertSent(BulkCreateCatalogVariantsRequest::class);
    $mockClient->assertNotSent(BulkUpdateCatalogItemsRequest::class);
    $mockClient->assertNotSent(UpdateCatalogItemRequest::class);
    $mockClient->assertNotSent(BulkUpdateCatalogVariantsRequest::class);
});

test('catalog service bulk creates missing variants when remote item exists without variants', function () {
    $currency = Currency::where('default', true)->first();
    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    \Lunar\Facades\StorefrontSession::setChannel($channel);
    \Lunar\Facades\StorefrontSession::setCustomerGroups(collect([$customerGroup]));

    $product = Product::factory()->create([
        'status' => 'published',
        'attribute_data' => [
            'name' => new TranslatedText(collect(['en' => 'Catalog Tee'])),
            'description' => new TranslatedText(collect(['en' => 'A nice tee'])),
        ],
    ]);

    $product->scheduleChannel($channel, now()->subDay());
    $product->scheduleCustomerGroup($customerGroup);

    $variant = ProductVariant::factory()->for($product)->create([
        'sku' => 'TEE-1',
        'stock' => 5,
    ]);

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 1999,
    ]);

    CatalogExternalIdStore::remember($product->id, 'TEE-1');

    $mockClient = new MockClient([
        CreateCatalogCategoryRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::uncategorized'],
        ], 201),
        UpdateCatalogItemRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-item',
                'id' => '$custom:::$default:::TEE-1',
            ],
        ], 200),
        BulkCreateCatalogVariantsRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-variant-bulk-create-job',
                'id' => 'variant-create-job-1',
            ],
        ], 202),
        GetCatalogItemVariantIdsRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoCatalogService($klaviyo))->syncProduct(
        $product->fresh(['variants', 'collections', 'brand', 'media'])
    );

    $mockClient->assertSent(UpdateCatalogItemRequest::class);
    $mockClient->assertSent(BulkCreateCatalogVariantsRequest::class);
    $mockClient->assertNotSent(BulkUpdateCatalogVariantsRequest::class);
});

test('catalog sync deletes orphan remote variants not in the current Lunar set', function () {
    $currency = Currency::where('default', true)->first();
    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    \Lunar\Facades\StorefrontSession::setChannel($channel);
    \Lunar\Facades\StorefrontSession::setCustomerGroups(collect([$customerGroup]));

    $product = Product::factory()->create([
        'status' => 'published',
        'attribute_data' => [
            'name' => new TranslatedText(collect(['en' => 'Catalog Tee'])),
            'description' => new TranslatedText(collect(['en' => 'A nice tee'])),
        ],
    ]);

    $product->scheduleChannel($channel, now()->subDay());
    $product->scheduleCustomerGroup($customerGroup);

    $variant = ProductVariant::factory()->for($product)->create([
        'sku' => 'TEE-1',
        'stock' => 5,
    ]);

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 1999,
    ]);

    $mockClient = new MockClient([
        CreateCatalogCategoryRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::uncategorized'],
        ], 201),
        UpdateCatalogItemRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-item',
                'id' => '$custom:::$default:::TEE-1',
            ],
        ], 200),
        UpdateCatalogVariantRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-variant',
                'id' => '$custom:::$default:::TEE-1',
            ],
        ], 200),
        GetCatalogItemVariantIdsRequest::class => MockResponse::make([
            'data' => [
                ['type' => 'catalog-variant', 'id' => '$custom:::$default:::LEGACY-VAR'],
                ['type' => 'catalog-variant', 'id' => '$custom:::$default:::TEE-1'],
            ],
            'links' => ['next' => null],
        ], 200),
        DeleteCatalogVariantRequest::class => MockResponse::make([], 204),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoCatalogService($klaviyo))->syncProduct(
        $product->fresh(['variants', 'collections', 'brand', 'media'])
    );

    $mockClient->assertSent(function ($request) {
        return $request instanceof DeleteCatalogVariantRequest
            && $request->resolveEndpoint() === '/catalog-variants/'.rawurlencode('$custom:::$default:::LEGACY-VAR').'/';
    });

    $mockClient->assertNotSent(function ($request) {
        return $request instanceof DeleteCatalogVariantRequest
            && $request->resolveEndpoint() === '/catalog-variants/'.rawurlencode('$custom:::$default:::TEE-1').'/';
    });
});

test('order service emits Placed Order and Ordered Product with catalog ProductID VariantID', function () {
    $currency = Currency::where('default', true)->first();
    $product = Product::factory()->create(['status' => 'published']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'ORD-SKU']);

    $order = Order::factory()->create([
        'currency_code' => $currency->code,
        'placed_at' => now(),
        'user_id' => null,
    ]);

    $country = Country::factory()->create();

    $order->addresses()->create([
        'type' => 'billing',
        'country_id' => $country->id,
        'first_name' => 'Test',
        'last_name' => 'User',
        'contact_email' => 'buyer@example.com',
    ]);

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'description' => 'Line',
        'identifier' => $variant->sku,
        'quantity' => 2,
        'unit_price' => 1000,
        'sub_total' => 2000,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 2000,
    ]);

    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);
    $profileService = new KlaviyoProfileService($klaviyo);
    $catalogService = new KlaviyoCatalogService($klaviyo);

    (new KlaviyoOrderService($profileService, $catalogService))->syncPlacedOrder($order->fresh([
        'user',
        'billingAddress',
        'currency',
        'productLines.purchasable.product.variants',
    ]));

    $mockClient->assertSent(function (CreateEventRequest $request) {
        $body = $request->body()->all();
        $name = $body['data']['attributes']['metric']['data']['attributes']['name'] ?? null;
        $props = $body['data']['attributes']['properties'] ?? [];

        return $name === 'Placed Order'
            && ($body['data']['attributes']['unique_id'] ?? null) === (string) ($props['OrderId'] ?? '')
            && ($props['Items'][0]['ProductID'] ?? null) === 'ORD-SKU'
            && ($props['Items'][0]['VariantID'] ?? null) === 'ORD-SKU'
            && array_key_exists('VariantID', $props['Items'][0] ?? [])
            && ! array_key_exists('ProductId', $props['Items'][0] ?? []);
    });

    $mockClient->assertSent(function (CreateEventRequest $request) use ($order) {
        $body = $request->body()->all();
        $name = $body['data']['attributes']['metric']['data']['attributes']['name'] ?? null;
        $props = $body['data']['attributes']['properties'] ?? [];
        $uniqueId = $body['data']['attributes']['unique_id'] ?? null;

        return $name === 'Ordered Product'
            && ($props['ProductID'] ?? null) === 'ORD-SKU'
            && ($props['VariantID'] ?? null) === 'ORD-SKU'
            && str_starts_with((string) $uniqueId, 'order:'.$order->id.':line:');
    });
});

test('klaviyo:sync-all-products dispatches SyncAllProductsToKlaviyo', function () {
    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);

    $this->artisan('klaviyo:sync-all-products', ['--chunk' => 50])
        ->assertSuccessful();

    Queue::assertPushed(SyncAllProductsToKlaviyo::class, function (SyncAllProductsToKlaviyo $job) {
        return $job->chunkSize === 50
            && $job->connection !== 'deferred';
    });
});

test('klaviyo:sync-all-products fails when sync_products disabled', function () {
    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);

    Config::set('lunar.klaviyo.sync_products', false);

    $this->artisan('klaviyo:sync-all-products')
        ->assertFailed();

    Queue::assertNotPushed(SyncAllProductsToKlaviyo::class);
});

test('SyncAllProductsToKlaviyo fans out delayed bulk chunk jobs for available products', function () {
    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    \Lunar\Facades\StorefrontSession::setChannel($channel);
    \Lunar\Facades\StorefrontSession::setCustomerGroups(collect([$customerGroup]));

    $available = [];

    foreach (['A', 'B', 'C'] as $suffix) {
        $product = Product::factory()->create(['status' => 'published']);
        $product->scheduleChannel($channel, now()->subDay());
        $product->scheduleCustomerGroup($customerGroup);
        ProductVariant::factory()->for($product)->create([
            'sku' => 'SYNC-'.$suffix,
            'stock' => 5,
        ]);
        $available[] = $product->id;
    }

    $draft = Product::factory()->create(['status' => 'draft']);
    ProductVariant::factory()->for($draft)->create([
        'sku' => 'SYNC-DRAFT',
        'stock' => 5,
    ]);

    $coordinator = new SyncAllProductsToKlaviyo(2);
    $coordinator->handle();

    $jobs = Queue::pushed(SyncProductsBulkToKlaviyo::class);

    expect($jobs)->toHaveCount(2)
        ->and($jobs[0]->productIds)->toBe(array_slice($available, 0, 2))
        ->and($jobs[1]->productIds)->toBe(array_slice($available, 2, 1))
        ->and($jobs[0]->delay)->toBeNull()
        ->and($jobs[1]->delay)->not->toBeNull()
        ->and($jobs[0]->timeout)->toBe(300)
        ->and($jobs[0]->connection)->not->toBe('deferred');

    Queue::assertNotPushed(SyncProductToKlaviyo::class);
});

test('SyncAllProductsToKlaviyo no-ops when catalog sync is disabled', function () {
    Config::set('lunar.klaviyo.sync_products', false);

    $mock = Mockery::mock(KlaviyoCatalogService::class);
    $mock->shouldNotReceive('syncProductsBulk');
    app()->instance(KlaviyoCatalogService::class, $mock);

    (new SyncAllProductsToKlaviyo)->handle();

    Queue::assertNotPushed(SyncProductsBulkToKlaviyo::class);
});

test('ensureCategory memoizes successful creates within a service instance', function () {
    $createCalls = 0;

    $mockClient = new MockClient([
        CreateCatalogCategoryRequest::class => function () use (&$createCalls) {
            $createCalls++;

            return MockResponse::make([
                'data' => ['id' => '$custom:::$default:::47'],
            ], 201);
        },
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);
    $catalog = new KlaviyoCatalogService($klaviyo);

    $first = $catalog->ensureCategory('47', 'Sale');
    $second = $catalog->ensureCategory('47', 'Sale');

    expect($createCalls)->toBe(1)
        ->and($first)->toBe($second)
        ->and($first['id'])->toBe('$custom:::$default:::47');
});

test('deleteAllCatalogItems lists pages then spawns bulk delete jobs', function () {
    $listCalls = 0;

    $mockClient = new MockClient([
        GetCatalogItemsRequest::class => function () use (&$listCalls) {
            $listCalls++;

            if ($listCalls === 1) {
                return MockResponse::make([
                    'data' => [
                        ['type' => 'catalog-item', 'id' => '$custom:::$default:::SKU-1'],
                        ['type' => 'catalog-item', 'id' => '$custom:::$default:::SKU-2'],
                    ],
                    'links' => [
                        'next' => 'https://a.klaviyo.com/api/catalog-items/?page%5Bcursor%5D=abc123',
                    ],
                ], 200);
            }

            return MockResponse::make([
                'data' => [
                    ['type' => 'catalog-item', 'id' => '$custom:::$default:::SKU-3'],
                ],
                'links' => [
                    'next' => null,
                ],
            ], 200);
        },
        BulkDeleteCatalogItemsRequest::class => MockResponse::make([
            'data' => [
                'type' => 'catalog-item-bulk-delete-job',
                'id' => 'job-1',
                'attributes' => [
                    'status' => 'queued',
                    'created_at' => '2026-08-27T00:00:00+00:00',
                    'total_count' => 3,
                ],
            ],
        ], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    $result = (new KlaviyoCatalogService($klaviyo))->deleteAllCatalogItems();

    expect($result)->toBe(['deleted' => 3, 'jobs' => 1]);

    $mockClient->assertSent(GetCatalogItemsRequest::class);
    $mockClient->assertSent(function (BulkDeleteCatalogItemsRequest $request) {
        $items = $request->body()->all()['data']['attributes']['items']['data'] ?? [];

        return count($items) === 3
            && ($items[0]['id'] ?? null) === '$custom:::$default:::SKU-1'
            && ($items[2]['id'] ?? null) === '$custom:::$default:::SKU-3';
    });
});

test('klaviyo:delete-all-products dispatches DeleteAllProductsFromKlaviyo', function () {
    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);

    $this->artisan('klaviyo:delete-all-products', ['--force' => true, '--page-size' => 50])
        ->assertSuccessful();

    Queue::assertPushed(DeleteAllProductsFromKlaviyo::class, function (DeleteAllProductsFromKlaviyo $job) {
        return $job->pageSize === 50
            && $job->connection !== 'deferred';
    });
});

test('klaviyo:delete-all-products fails when disabled', function () {
    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);

    Config::set('lunar.klaviyo.enabled', false);

    $this->artisan('klaviyo:delete-all-products', ['--force' => true])
        ->assertFailed();

    Queue::assertNotPushed(DeleteAllProductsFromKlaviyo::class);
});
