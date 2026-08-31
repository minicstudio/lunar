<?php

uses(\Lunar\Tests\Core\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Enums\Marketing\MarketingConsentSource;
use Lunar\Enums\Marketing\MarketingSubscriptionMode;
use Lunar\Events\Marketing\CustomerMarketingConsentGranted;
use Lunar\Events\Marketing\CustomerMarketingProfileUpdated;
use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Klaviyo\Connectors\KlaviyoConnector;
use Lunar\Klaviyo\Jobs\SubscribeProfileToKlaviyo;
use Lunar\Klaviyo\Jobs\SyncProfileToKlaviyo;
use Lunar\Klaviyo\Jobs\TrackEventToKlaviyo;
use Lunar\Klaviyo\Listeners\SubscribeProfileOnMarketingConsentGranted;
use Lunar\Klaviyo\Listeners\SyncProfileOnMarketingProfileUpdated;
use Lunar\Klaviyo\Listeners\TrackEventOnStorefrontMarketingEventOccurred;
use Lunar\Klaviyo\Requests\CreateEventRequest;
use Lunar\Klaviyo\Requests\GetProfilesRequest;
use Lunar\Klaviyo\Requests\SubscribeProfilesRequest;
use Lunar\Klaviyo\Requests\UpsertProfileRequest;
use Lunar\Klaviyo\Services\KlaviyoProfileService;
use Lunar\Klaviyo\Services\KlaviyoService;
use Lunar\Models\Customer;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Language::factory()->create(['default' => true, 'code' => 'en']);

    Queue::fake();

    Config::set('lunar.klaviyo.enabled', true);
    Config::set('lunar.klaviyo.api_key', 'pk_test');
    Config::set('lunar.klaviyo.api_revision', '2026-01-15');
    Config::set('lunar.klaviyo.list_id', 'list_doi');
    Config::set('lunar.klaviyo.automatic_list_id', 'list_single');
    Config::set('lunar.klaviyo.sync_subscribers', true);
    Config::set('lunar.klaviyo.track_events', true);
    Config::set('lunar.klaviyo.profile_attributes.language', 'language');
    Config::set('lunar.klaviyo.queue_connection', 'deferred');
});

test('default api_revision is not the retiring 2024-10-15 pin', function () {
    expect(config('lunar.klaviyo.api_revision'))->not->toBe('2024-10-15')
        ->and(config('lunar.klaviyo.api_revision'))->toBe('2026-01-15');
});

test('package config defaults queue_connection to deferred', function () {
    $config = require dirname(__DIR__, 3).'/packages/klaviyo/config/klaviyo.php';

    expect($config['queue_connection'])->toBe('deferred');
});

test('package registers middleware that executes deferred jobs', function () {
    $kernel = app(HttpKernel::class);
    $middlewareProperty = new ReflectionProperty(\Illuminate\Foundation\Http\Kernel::class, 'middleware');

    expect($middlewareProperty->getValue($kernel))->toContain(InvokeDeferredCallbacks::class);
});

test('connector sends JSON:API media types', function () {
    $connector = new KlaviyoConnector(apiKey: 'pk_test', revision: '2026-01-15');
    $headers = $connector->headers()->all();

    expect($headers['Accept'] ?? null)->toBe('application/vnd.api+json')
        ->and($headers['Content-Type'] ?? null)->toBe('application/vnd.api+json')
        ->and($headers['revision'] ?? null)->toBe('2026-01-15');
});

test('consent listener dispatches SubscribeProfileToKlaviyo on deferred connection', function () {
    $event = new CustomerMarketingConsentGranted(
        email: 'a@example.com',
        source: MarketingConsentSource::Newsletter,
        subscriptionMode: MarketingSubscriptionMode::ExplicitOptIn,
    );

    (new SubscribeProfileOnMarketingConsentGranted)->handle($event);

    Queue::assertPushed(SubscribeProfileToKlaviyo::class, function (SubscribeProfileToKlaviyo $job) {
        return $job->email === 'a@example.com'
            && $job->subscriptionMode === MarketingSubscriptionMode::ExplicitOptIn
            && $job->connection === 'deferred';
    });
});

test('consent listener no-ops when klaviyo disabled', function () {
    Config::set('lunar.klaviyo.enabled', false);

    $event = new CustomerMarketingConsentGranted(
        email: 'a@example.com',
        source: MarketingConsentSource::Newsletter,
        subscriptionMode: MarketingSubscriptionMode::ExplicitOptIn,
    );

    (new SubscribeProfileOnMarketingConsentGranted)->handle($event);

    Queue::assertNothingPushed();
});

test('profile listener dispatches SyncProfileToKlaviyo when sync_subscribers enabled', function () {
    $customer = Mockery::mock(Customer::class);
    $customer->shouldReceive('getAttribute')->with('id')->andReturn(42);
    $customer->shouldReceive('offsetExists')->andReturn(false);

    $event = new CustomerMarketingProfileUpdated(
        customer: $customer,
        properties: ['language' => 'hu'],
    );

    (new SyncProfileOnMarketingProfileUpdated)->handle($event);

    Queue::assertPushed(SyncProfileToKlaviyo::class, function (SyncProfileToKlaviyo $job) {
        return $job->connection === 'deferred';
    });
});

test('storefront listener passes stable eventId to TrackEventToKlaviyo', function () {
    $event = new StorefrontMarketingEventOccurred(
        email: 'a@example.com',
        eventName: 'begin_checkout',
        properties: ['cart_id' => '1'],
        uniqueKey: 'begin_checkout:cart:1',
    );

    (new TrackEventOnStorefrontMarketingEventOccurred)->handle($event);

    Queue::assertPushed(TrackEventToKlaviyo::class, function (TrackEventToKlaviyo $job) {
        return $job->eventId === 'begin_checkout:cart:1'
            && $job->connection === 'deferred';
    });
});

test('TrackEventToKlaviyo preserves eventId across construction and does not regenerate in handle', function () {
    $job = new TrackEventToKlaviyo(
        email: 'a@example.com',
        eventName: 'view_item',
        properties: [],
        eventId: 'stable-id-1',
    );

    expect($job->eventId)->toBe('stable-id-1');

    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $service = new KlaviyoService;
    $service->getConnector()->withMockClient($mockClient);
    app()->instance(KlaviyoService::class, $service);

    $job->handle();

    $mockClient->assertSent(function (CreateEventRequest $request) {
        $body = $request->body()->all();

        return ($body['data']['attributes']['unique_id'] ?? null) === 'stable-id-1';
    });
});

test('TrackEventToKlaviyo rewrites product_id to catalog item SKU', function () {
    $product = Product::factory()->create(['status' => 'published']);
    ProductVariant::factory()->for($product)->create(['sku' => 'VIEW-SKU']);

    $job = new TrackEventToKlaviyo(
        email: 'a@example.com',
        eventName: 'view_item',
        properties: [
            'product_id' => (string) $product->id,
            'sku' => 'VIEW-SKU',
            'variant_id' => '99',
        ],
        eventId: 'view-stable-1',
    );

    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $service = new KlaviyoService;
    $service->getConnector()->withMockClient($mockClient);
    app()->instance(KlaviyoService::class, $service);

    $job->handle();

    $mockClient->assertSent(function (CreateEventRequest $request) {
        $body = $request->body()->all();
        $props = $body['data']['attributes']['properties'] ?? [];

        return ($body['data']['attributes']['unique_id'] ?? null) === 'view-stable-1'
            && ($props['product_id'] ?? null) === 'VIEW-SKU'
            && ($props['variant_id'] ?? null) === 'VIEW-SKU'
            && ($props['sku'] ?? null) === 'VIEW-SKU';
    });
});

test('TrackEventToKlaviyo rewrites variant_id from DB id to SKU when sku property missing', function () {
    $product = Product::factory()->create(['status' => 'published']);
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'VAR-ONLY']);

    $job = new TrackEventToKlaviyo(
        email: 'a@example.com',
        eventName: 'add_to_cart',
        properties: [
            'product_id' => (string) $product->id,
            'variant_id' => (string) $variant->id,
        ],
        eventId: 'add-variant-1',
    );

    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $service = new KlaviyoService;
    $service->getConnector()->withMockClient($mockClient);
    app()->instance(KlaviyoService::class, $service);

    $job->handle();

    $mockClient->assertSent(function (CreateEventRequest $request) {
        $props = $request->body()->all()['data']['attributes']['properties'] ?? [];

        return ($props['product_id'] ?? null) === 'VAR-ONLY'
            && ($props['variant_id'] ?? null) === 'VAR-ONLY';
    });
});

test('TrackEventToKlaviyo rewrites begin_checkout product_id_n keys to catalog SKUs', function () {
    $productA = Product::factory()->create(['status' => 'published']);
    $productB = Product::factory()->create(['status' => 'published']);
    ProductVariant::factory()->for($productA)->create(['sku' => 'CHK-A']);
    ProductVariant::factory()->for($productB)->create(['sku' => 'CHK-B']);

    $job = new TrackEventToKlaviyo(
        email: 'a@example.com',
        eventName: 'begin_checkout',
        properties: [
            'cart_id' => '1',
            'product_id_1' => (string) $productA->id,
            'sku_1' => 'CHK-A',
            'product_id_2' => (string) $productB->id,
            'sku_2' => 'CHK-B',
        ],
        eventId: 'begin_checkout:cart:1',
    );

    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $service = new KlaviyoService;
    $service->getConnector()->withMockClient($mockClient);
    app()->instance(KlaviyoService::class, $service);

    $job->handle();

    $mockClient->assertSent(function (CreateEventRequest $request) {
        $props = $request->body()->all()['data']['attributes']['properties'] ?? [];

        return ($props['product_id_1'] ?? null) === 'CHK-A'
            && ($props['product_id_2'] ?? null) === 'CHK-B';
    });
});

test('TrackEventToKlaviyo uses first variant SKU as catalog identity for multi-variant products', function () {
    $product = Product::factory()->create(['status' => 'published']);
    ProductVariant::factory()->for($product)->create(['sku' => 'FIRST-SKU']);
    ProductVariant::factory()->for($product)->create(['sku' => 'SECOND-SKU']);

    $job = new TrackEventToKlaviyo(
        email: 'a@example.com',
        eventName: 'view_item',
        properties: [
            'product_id' => (string) $product->id,
            'variant_id' => '42',
            'sku' => 'SECOND-SKU',
        ],
        eventId: 'view-multi-1',
    );

    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $service = new KlaviyoService;
    $service->getConnector()->withMockClient($mockClient);
    app()->instance(KlaviyoService::class, $service);

    $job->handle();

    $mockClient->assertSent(function (CreateEventRequest $request) {
        $props = $request->body()->all()['data']['attributes']['properties'] ?? [];

        // Catalog item external_id is first non-empty variant SKU (same as orders/catalog).
        // Event variant_id uses the viewed variant's SKU from the sku property.
        return ($props['product_id'] ?? null) === 'FIRST-SKU'
            && ($props['variant_id'] ?? null) === 'SECOND-SKU'
            && ($props['sku'] ?? null) === 'SECOND-SKU';
    });
});
test('KlaviyoProfileService trackEvent sends unique_id equal to eventId', function () {
    $mockClient = new MockClient([
        CreateEventRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    $profileService = new KlaviyoProfileService($klaviyo);
    $profileService->trackEvent('a@example.com', 'add_to_cart', ['sku' => 'ABC'], 'evt-99');

    $mockClient->assertSent(function (CreateEventRequest $request) {
        $body = $request->body()->all();

        return ($body['data']['attributes']['unique_id'] ?? null) === 'evt-99'
            && ($body['data']['attributes']['metric']['data']['attributes']['name'] ?? null) === 'add_to_cart';
    });
});

test('subscribe ExplicitOptIn uses list_id and does not set historical_import', function () {
    $mockClient = new MockClient([
        UpsertProfileRequest::class => MockResponse::make([], 200),
        SubscribeProfilesRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoProfileService($klaviyo))->subscribe(
        email: 'a@example.com',
        subscriptionMode: MarketingSubscriptionMode::ExplicitOptIn,
    );

    $mockClient->assertSent(function (SubscribeProfilesRequest $request) {
        $body = $request->body()->all();
        $attributes = $body['data']['attributes'] ?? [];

        return ! array_key_exists('historical_import', $attributes)
            && ($attributes['profiles']['data'][0]['attributes']['subscriptions']['email']['marketing']['consent'] ?? null) === 'SUBSCRIBED'
            && ! array_key_exists('consented_at', $attributes['profiles']['data'][0]['attributes']['subscriptions']['email']['marketing'] ?? [])
            && ($body['data']['relationships']['list']['data']['id'] ?? null) === 'list_doi';
    });
});

test('subscribe upserts language from context locale', function () {
    $mockClient = new MockClient([
        UpsertProfileRequest::class => MockResponse::make([], 200),
        SubscribeProfilesRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoProfileService($klaviyo))->subscribe(
        email: 'a@example.com',
        subscriptionMode: MarketingSubscriptionMode::ExplicitOptIn,
        context: ['locale' => 'hu'],
    );

    $mockClient->assertSent(function ($request) {
        if (! $request instanceof UpsertProfileRequest) {
            return false;
        }

        $body = $request->body()->all();
        $attributes = $body['data']['attributes'] ?? [];

        return ($attributes['email'] ?? null) === 'a@example.com'
            && ($attributes['properties']['language'] ?? null) === 'hu';
    });
});

test('subscribe upserts language from app locale when context omits it', function () {
    app()->setLocale('de');

    $mockClient = new MockClient([
        UpsertProfileRequest::class => MockResponse::make([], 200),
        SubscribeProfilesRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoProfileService($klaviyo))->subscribe(
        email: 'a@example.com',
        subscriptionMode: MarketingSubscriptionMode::ExplicitOptIn,
    );

    $mockClient->assertSent(function ($request) {
        if (! $request instanceof UpsertProfileRequest) {
            return false;
        }

        $body = $request->body()->all();

        return ($body['data']['attributes']['properties']['language'] ?? null) === 'de';
    });
});

test('subscribe CustomerRegistration uses automatic_list_id without historical_import', function () {
    $mockClient = new MockClient([
        UpsertProfileRequest::class => MockResponse::make([], 200),
        GetProfilesRequest::class => MockResponse::make(['data' => []], 200),
        SubscribeProfilesRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoProfileService($klaviyo))->subscribe(
        email: 'a@example.com',
        subscriptionMode: MarketingSubscriptionMode::CustomerRegistration,
    );

    $mockClient->assertSent(function (SubscribeProfilesRequest $request) {
        $body = $request->body()->all();
        $attributes = $body['data']['attributes'] ?? [];
        $marketing = $attributes['profiles']['data'][0]['attributes']['subscriptions']['email']['marketing'] ?? [];

        return ! array_key_exists('historical_import', $attributes)
            && ! array_key_exists('consented_at', $marketing)
            && ($marketing['consent'] ?? null) === 'SUBSCRIBED'
            && ($body['data']['relationships']['list']['data']['id'] ?? null) === 'list_single';
    });
});

test('subscribe CustomerRegistration skips suppressed profiles', function () {
    $mockClient = new MockClient([
        UpsertProfileRequest::class => MockResponse::make([], 200),
        GetProfilesRequest::class => MockResponse::make([
            'data' => [
                [
                    'type' => 'profile',
                    'id' => '01ABC',
                    'attributes' => [
                        'email' => 'a@example.com',
                        'subscriptions' => [
                            'email' => [
                                'marketing' => [
                                    'consent' => 'UNSUBSCRIBED',
                                    'suppression' => [
                                        ['reason' => 'UNSUBSCRIBE'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    $result = (new KlaviyoProfileService($klaviyo))->subscribe(
        email: 'a@example.com',
        subscriptionMode: MarketingSubscriptionMode::CustomerRegistration,
    );

    expect($result['skipped'] ?? false)->toBeTrue();

    $mockClient->assertNotSent(SubscribeProfilesRequest::class);
});
