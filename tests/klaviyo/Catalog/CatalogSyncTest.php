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
use Lunar\Klaviyo\Jobs\DeleteCatalogVariantFromKlaviyo;
use Lunar\Klaviyo\Jobs\SyncAllProductsToKlaviyo;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Listeners\SyncProductOnCollectionsUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnDeleted;
use Lunar\Klaviyo\Listeners\SyncProductOnPublished;
use Lunar\Klaviyo\Listeners\SyncProductOnUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnVariantCreated;
use Lunar\Klaviyo\Listeners\SyncProductOnVariantDeleted;
use Lunar\Klaviyo\Requests\BulkDeleteCatalogItemsRequest;
use Lunar\Klaviyo\Requests\CreateCatalogCategoryRequest;
use Lunar\Klaviyo\Requests\CreateCatalogItemRequest;
use Lunar\Klaviyo\Requests\CreateCatalogVariantRequest;
use Lunar\Klaviyo\Requests\CreateEventRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogItemRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogVariantRequest;
use Lunar\Klaviyo\Requests\GetCatalogItemsRequest;
use Lunar\Klaviyo\Requests\GetCatalogItemVariantIdsRequest;
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
    Config::set('app.url', 'https://shop.test');
});

test('published listener dispatches SyncProductToKlaviyo with CREATE', function () {
    $product = Product::factory()->create(['status' => 'published']);

    (new SyncProductOnPublished)->handle(new ProductPublished($product));

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->productId === $product->id
            && $job->eventType === ProductEventType::CREATE;
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

    Queue::assertPushed(DeleteCatalogVariantFromKlaviyo::class, function (DeleteCatalogVariantFromKlaviyo $job) use ($variant, $product) {
        return $job->variantExternalId === (string) $variant->id
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
        CreateCatalogItemRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::TEE-1'],
        ], 201),
        CreateCatalogVariantRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::'.$variant->id],
        ], 201),
        GetCatalogItemVariantIdsRequest::class => MockResponse::make([
            'data' => [
                ['type' => 'catalog-variant', 'id' => '$custom:::$default:::'.$variant->id],
            ],
            'links' => ['next' => null],
        ], 200),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    expect($product->fresh()->isAvailable())->toBeTrue();

    $result = (new KlaviyoCatalogService($klaviyo))->syncProduct(
        $product->fresh(['variants', 'collections', 'brand', 'media'])
    );

    expect($result)->not->toBeEmpty()
        ->and(CatalogExternalIdStore::get($product->id))->toBe('TEE-1');

    $mockClient->assertSent(CreateCatalogItemRequest::class);
    $mockClient->assertSent(CreateCatalogVariantRequest::class);

    $mockClient->assertSent(function ($request) {
        return $request instanceof CreateCatalogItemRequest
            && ($request->body()->all()['data']['attributes']['external_id'] ?? null) === 'TEE-1';
    });

    $mockClient->assertSent(function ($request) use ($variant) {
        if (! $request instanceof CreateCatalogVariantRequest) {
            return false;
        }

        $body = $request->body()->all();

        return ($body['data']['attributes']['external_id'] ?? null) === (string) $variant->id
            && ($body['data']['relationships']['item']['data']['id'] ?? null) === '$custom:::$default:::TEE-1';
    });
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
        CreateCatalogItemRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::TEE-1'],
        ], 201),
        CreateCatalogVariantRequest::class => MockResponse::make([
            'data' => ['id' => '$custom:::$default:::'.$variant->id],
        ], 201),
        GetCatalogItemVariantIdsRequest::class => MockResponse::make([
            'data' => [
                ['type' => 'catalog-variant', 'id' => '$custom:::$default:::21'],
                ['type' => 'catalog-variant', 'id' => '$custom:::$default:::'.$variant->id],
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
            && $request->resolveEndpoint() === '/catalog-variants/'.rawurlencode('$custom:::$default:::21').'/';
    });

    $mockClient->assertNotSent(function ($request) use ($variant) {
        return $request instanceof DeleteCatalogVariantRequest
            && $request->resolveEndpoint() === '/catalog-variants/'.rawurlencode('$custom:::$default:::'.$variant->id).'/';
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
            && str_starts_with((string) $uniqueId, 'order:'.$order->id.':line:');
    });
});

test('klaviyo:sync-all-products dispatches SyncAllProductsToKlaviyo', function () {
    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);

    $this->artisan('klaviyo:sync-all-products', ['--chunk' => 50])
        ->assertSuccessful();

    Queue::assertPushed(SyncAllProductsToKlaviyo::class, function (SyncAllProductsToKlaviyo $job) {
        return $job->chunkSize === 50;
    });
});

test('klaviyo:sync-all-products fails when sync_products disabled', function () {
    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);

    Config::set('lunar.klaviyo.sync_products', false);

    $this->artisan('klaviyo:sync-all-products')
        ->assertFailed();

    Queue::assertNotPushed(SyncAllProductsToKlaviyo::class);
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

test('klaviyo:delete-all-products requires confirmation unless --force', function () {
    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);

    $mockClient = new MockClient([
        GetCatalogItemsRequest::class => MockResponse::make([
            'data' => [],
            'links' => ['next' => null],
        ], 200),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);
    $this->app->instance(KlaviyoService::class, $klaviyo);
    $this->app->instance(KlaviyoCatalogService::class, new KlaviyoCatalogService($klaviyo));

    $this->artisan('klaviyo:delete-all-products', ['--force' => true])
        ->assertSuccessful();
});

test('klaviyo:delete-all-products fails when disabled', function () {
    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);

    Config::set('lunar.klaviyo.enabled', false);

    $this->artisan('klaviyo:delete-all-products', ['--force' => true])
        ->assertFailed();
});
