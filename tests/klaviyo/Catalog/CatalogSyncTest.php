<?php

uses(\Lunar\Tests\Core\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Enums\ProductEventType;
use Lunar\Events\ProductDeletedEvent;
use Lunar\Events\ProductPublished;
use Lunar\Events\ProductUpdatedEvent;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Klaviyo\Jobs\SyncAllProductsToKlaviyo;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Listeners\SyncProductOnDeleted;
use Lunar\Klaviyo\Listeners\SyncProductOnPublished;
use Lunar\Klaviyo\Listeners\SyncProductOnUpdated;
use Lunar\Klaviyo\Requests\CreateCatalogCategoryRequest;
use Lunar\Klaviyo\Requests\CreateCatalogItemRequest;
use Lunar\Klaviyo\Requests\CreateCatalogVariantRequest;
use Lunar\Klaviyo\Requests\DeleteCatalogItemRequest;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Services\KlaviyoService;
use Lunar\Models\Currency;
use Lunar\Models\Language;
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
    Config::set('lunar.klaviyo.api_revision', '2024-10-15');
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
        return $job->product->is($product)
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
        return $job->product->is($product)
            && $job->eventType === ProductEventType::UPDATE;
    });
});

test('deleted listener dispatches SyncProductToKlaviyo with DELETE', function () {
    $product = Product::factory()->create();

    (new SyncProductOnDeleted)->handle(new ProductDeletedEvent($product));

    Queue::assertPushed(SyncProductToKlaviyo::class, function (SyncProductToKlaviyo $job) use ($product) {
        return $job->product->is($product)
            && $job->eventType === ProductEventType::DELETE;
    });
});

test('SyncProductToKlaviyo unique id is stable per product', function () {
    $product = Product::factory()->create();

    $job = new SyncProductToKlaviyo($product, ProductEventType::UPDATE);

    expect($job->uniqueId())->toBe('klaviyo-product-sync-'.$product->id)
        ->and($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([60, 300, 3600]);
});

test('SyncProductToKlaviyo calls syncProduct for CREATE and UPDATE', function () {
    $product = Product::factory()->create(['status' => 'published']);

    $mock = Mockery::mock(KlaviyoCatalogService::class);
    $mock->shouldReceive('syncProduct')->once()->with(Mockery::on(fn ($p) => $p->is($product)))->andReturn([]);
    app()->instance(KlaviyoCatalogService::class, $mock);

    (new SyncProductToKlaviyo($product, ProductEventType::CREATE))->handle();
});

test('SyncProductToKlaviyo calls deleteProduct for DELETE', function () {
    $product = Product::factory()->create();

    $mock = Mockery::mock(KlaviyoCatalogService::class);
    $mock->shouldReceive('deleteProduct')->once()->with(Mockery::on(fn ($p) => $p->is($product)))->andReturn(true);
    app()->instance(KlaviyoCatalogService::class, $mock);

    (new SyncProductToKlaviyo($product, ProductEventType::DELETE))->handle();
});

test('SyncProductToKlaviyo no-ops when disabled', function () {
    Config::set('lunar.klaviyo.enabled', false);

    $product = Product::factory()->create();

    $mock = Mockery::mock(KlaviyoCatalogService::class);
    $mock->shouldNotReceive('syncProduct');
    $mock->shouldNotReceive('deleteProduct');
    app()->instance(KlaviyoCatalogService::class, $mock);

    (new SyncProductToKlaviyo($product))->handle();
});

test('catalog service deletes unpublished products', function () {
    $product = Product::factory()->create(['status' => 'draft']);

    $mockClient = new MockClient([
        DeleteCatalogItemRequest::class => MockResponse::make([], 204),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    $result = (new KlaviyoCatalogService($klaviyo))->syncProduct($product);

    expect($result)->toBeArray()->toBeEmpty();

    $mockClient->assertSent(DeleteCatalogItemRequest::class);
});

test('catalog service deleteProduct treats 404 as success', function () {
    $product = Product::factory()->create();

    $mockClient = new MockClient([
        DeleteCatalogItemRequest::class => MockResponse::make([], 404),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    expect((new KlaviyoCatalogService($klaviyo))->deleteProduct($product))->toBeTrue();
});

test('catalog service upserts item and variants with distinct external ids', function () {
    $currency = Currency::where('default', true)->first();
    $channel = \Lunar\Models\Channel::factory()->create(['default' => true]);
    $customerGroup = \Lunar\Models\CustomerGroup::factory()->create(['default' => true]);

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
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    expect($product->fresh()->isAvailable())->toBeTrue();

    $result = (new KlaviyoCatalogService($klaviyo))->syncProduct(
        $product->fresh(['variants', 'collections', 'brand', 'media'])
    );

    expect($result)->not->toBeEmpty();

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
