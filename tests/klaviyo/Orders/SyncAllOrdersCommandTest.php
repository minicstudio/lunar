<?php

uses(\Lunar\Tests\Core\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Klaviyo\Jobs\SyncOrderToKlaviyo;
use Lunar\Klaviyo\Requests\CreateEventRequest;
use Lunar\Klaviyo\Services\KlaviyoService;
use Lunar\Models\Country;
use Lunar\Models\Currency;
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
    Config::set('lunar.klaviyo.sync_orders', true);
    Config::set('lunar.klaviyo.queue_connection', 'deferred');

    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);
});

test('klaviyo:sync-all-orders fails when enabled is false', function () {
    Config::set('lunar.klaviyo.enabled', false);

    $this->artisan('klaviyo:sync-all-orders')
        ->expectsOutputToContain('KLAVIYO_ENABLED=true')
        ->assertFailed();

    Queue::assertNotPushed(SyncOrderToKlaviyo::class);
});

test('klaviyo:sync-all-orders fails when sync_orders is false', function () {
    Config::set('lunar.klaviyo.sync_orders', false);

    $this->artisan('klaviyo:sync-all-orders')
        ->expectsOutputToContain('KLAVIYO_SYNC_ORDERS=true')
        ->assertFailed();

    Queue::assertNotPushed(SyncOrderToKlaviyo::class);
});

test('klaviyo:sync-all-orders syncs placed orders with event time and skips drafts', function () {
    $currency = Currency::where('default', true)->first();
    $product = Product::factory()->create(['status' => 'published']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'CMD-ORD-SKU']);
    $placedAt = now()->subDays(5)->seconds(0);
    $country = Country::factory()->create();

    $placedOrder = Order::factory()->create([
        'currency_code' => $currency->code,
        'placed_at' => $placedAt,
        'user_id' => null,
        'reference' => 'REF-PLACED',
    ]);

    $placedOrder->addresses()->create([
        'type' => 'billing',
        'country_id' => $country->id,
        'first_name' => 'Test',
        'last_name' => 'Buyer',
        'contact_email' => 'cmd-buyer@example.com',
    ]);

    OrderLine::factory()->create([
        'order_id' => $placedOrder->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'description' => 'Line',
        'identifier' => $variant->sku,
        'quantity' => 1,
        'unit_price' => 1500,
        'sub_total' => 1500,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 1500,
    ]);

    Order::factory()->create([
        'currency_code' => $currency->code,
        'placed_at' => null,
        'user_id' => null,
        'reference' => 'REF-DRAFT',
    ]);

    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);
    app()->instance(KlaviyoService::class, $klaviyo);

    $this->artisan('klaviyo:sync-all-orders', ['--chunk' => 10])
        ->expectsConfirmation('Do you want to proceed with syncing all orders to Klaviyo?', 'yes')
        ->assertSuccessful();

    $placedOrder = $placedOrder->fresh();
    $expectedTime = $placedOrder->placed_at->format(DATE_ATOM);

    $mockClient->assertSent(function (CreateEventRequest $request) use ($placedOrder, $expectedTime) {
        $body = $request->body()->all();
        $name = $body['data']['attributes']['metric']['data']['attributes']['name'] ?? null;
        $props = $body['data']['attributes']['properties'] ?? [];

        return $name === 'Placed Order'
            && ($body['data']['attributes']['unique_id'] ?? null) === (string) $placedOrder->id
            && ($body['data']['attributes']['time'] ?? null) === $expectedTime
            && ($props['Items'][0]['ProductID'] ?? null) === 'CMD-ORD-SKU'
            && ($props['Items'][0]['VariantID'] ?? null) === 'CMD-ORD-SKU';
    });

    $mockClient->assertSent(function (CreateEventRequest $request) use ($placedOrder, $expectedTime) {
        $body = $request->body()->all();
        $name = $body['data']['attributes']['metric']['data']['attributes']['name'] ?? null;
        $props = $body['data']['attributes']['properties'] ?? [];

        return $name === 'Ordered Product'
            && ($body['data']['attributes']['time'] ?? null) === $expectedTime
            && ($props['ProductID'] ?? null) === 'CMD-ORD-SKU'
            && ($props['VariantID'] ?? null) === 'CMD-ORD-SKU'
            && str_starts_with((string) ($body['data']['attributes']['unique_id'] ?? ''), 'order:'.$placedOrder->id.':line:');
    });

    $mockClient->assertSentCount(2);

    Queue::assertNotPushed(SyncOrderToKlaviyo::class);
});

test('klaviyo:sync-all-orders skips missing-email orders and still succeeds', function () {
    $currency = Currency::where('default', true)->first();
    $country = Country::factory()->create();

    $missingEmailOrder = Order::factory()->create([
        'currency_code' => $currency->code,
        'placed_at' => now()->subDay(),
        'user_id' => null,
        'reference' => 'REF-NO-EMAIL',
    ]);

    $missingEmailOrder->addresses()->create([
        'type' => 'billing',
        'country_id' => $country->id,
        'first_name' => 'No',
        'last_name' => 'Email',
        'contact_email' => null,
    ]);

    $product = Product::factory()->create(['status' => 'published']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'CMD-OK-SKU']);

    $okOrder = Order::factory()->create([
        'currency_code' => $currency->code,
        'placed_at' => now()->subHours(2),
        'user_id' => null,
        'reference' => 'REF-OK',
    ]);

    $okOrder->addresses()->create([
        'type' => 'billing',
        'country_id' => $country->id,
        'first_name' => 'Ok',
        'last_name' => 'Buyer',
        'contact_email' => 'ok@example.com',
    ]);

    OrderLine::factory()->create([
        'order_id' => $okOrder->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'description' => 'Line',
        'identifier' => $variant->sku,
        'quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 1000,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 1000,
    ]);

    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);
    app()->instance(KlaviyoService::class, $klaviyo);

    $this->artisan('klaviyo:sync-all-orders', ['--chunk' => 10])
        ->expectsConfirmation('Do you want to proceed with syncing all orders to Klaviyo?', 'yes')
        ->expectsOutputToContain('Skipped')
        ->assertSuccessful();

    $mockClient->assertSentCount(2);

    Queue::assertNotPushed(SyncOrderToKlaviyo::class);
});

test('order service falls back to currency_code when currency relation is missing', function () {
    $product = Product::factory()->create(['status' => 'published']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'CUR-FALLBACK']);
    $placedAt = now()->subDays(2)->seconds(0);

    $order = Order::factory()->create([
        'currency_code' => 'XYZ',
        'placed_at' => $placedAt,
        'user_id' => null,
    ]);

    $country = Country::factory()->create();

    $order->addresses()->create([
        'type' => 'billing',
        'country_id' => $country->id,
        'first_name' => 'Test',
        'last_name' => 'User',
        'contact_email' => 'currency@example.com',
    ]);

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'description' => 'Line',
        'identifier' => $variant->sku,
        'quantity' => 1,
        'unit_price' => 1000,
        'sub_total' => 1000,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 1000,
    ]);

    expect($order->fresh()->currency)->toBeNull();

    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);
    $profileService = new \Lunar\Klaviyo\Services\KlaviyoProfileService($klaviyo);
    $catalogService = new \Lunar\Klaviyo\Services\KlaviyoCatalogService($klaviyo);

    (new \Lunar\Klaviyo\Services\KlaviyoOrderService($profileService, $catalogService))->syncPlacedOrder(
        $order->fresh(['user', 'billingAddress', 'currency', 'productLines.purchasable.product.variants'])
    );

    $mockClient->assertSent(function (CreateEventRequest $request) {
        $body = $request->body()->all();
        $name = $body['data']['attributes']['metric']['data']['attributes']['name'] ?? null;

        return $name === 'Placed Order'
            && ($body['data']['attributes']['value_currency'] ?? null) === 'XYZ';
    });
});
