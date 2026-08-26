<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Enums\Marketing\MarketingConsentSource;
use Lunar\Enums\Marketing\MarketingSubscriptionMode;
use Lunar\Events\Marketing\CustomerMarketingConsentGranted;
use Lunar\Events\Marketing\CustomerMarketingProfileUpdated;
use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Klaviyo\Jobs\SubscribeProfileToKlaviyo;
use Lunar\Klaviyo\Jobs\SyncProfileToKlaviyo;
use Lunar\Klaviyo\Jobs\TrackEventToKlaviyo;
use Lunar\Klaviyo\Listeners\SubscribeProfileOnMarketingConsentGranted;
use Lunar\Klaviyo\Listeners\SyncProfileOnMarketingProfileUpdated;
use Lunar\Klaviyo\Listeners\TrackEventOnStorefrontMarketingEventOccurred;
use Lunar\Klaviyo\Requests\CreateEventRequest;
use Lunar\Klaviyo\Requests\SubscribeProfilesRequest;
use Lunar\Klaviyo\Requests\UpsertProfileRequest;
use Lunar\Klaviyo\Services\KlaviyoProfileService;
use Lunar\Klaviyo\Services\KlaviyoService;
use Lunar\Models\Customer;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Queue::fake();

    Config::set('lunar.klaviyo.enabled', true);
    Config::set('lunar.klaviyo.api_key', 'pk_test');
    Config::set('lunar.klaviyo.api_revision', '2024-10-15');
    Config::set('lunar.klaviyo.list_id', 'list_123');
    Config::set('lunar.klaviyo.sync_subscribers', true);
    Config::set('lunar.klaviyo.track_events', true);
    Config::set('lunar.klaviyo.profile_attributes.language', 'language');
});

test('consent listener dispatches SubscribeProfileToKlaviyo when enabled', function () {
    $event = new CustomerMarketingConsentGranted(
        email: 'a@example.com',
        source: MarketingConsentSource::Newsletter,
        subscriptionMode: MarketingSubscriptionMode::ExplicitOptIn,
    );

    (new SubscribeProfileOnMarketingConsentGranted)->handle($event);

    Queue::assertPushed(SubscribeProfileToKlaviyo::class, function (SubscribeProfileToKlaviyo $job) {
        return $job->email === 'a@example.com'
            && $job->subscriptionMode === MarketingSubscriptionMode::ExplicitOptIn;
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

    Queue::assertPushed(SyncProfileToKlaviyo::class);
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
        return $job->eventId === 'begin_checkout:cart:1';
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

test('subscribe ExplicitOptIn does not set historical_import', function () {
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
            && ! array_key_exists('consented_at', $attributes['profiles']['data'][0]['attributes']['subscriptions']['email']['marketing'] ?? []);
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

test('subscribe CustomerRegistration sets historical_import and past consented_at', function () {
    $consentedAt = now()->subHour();

    $mockClient = new MockClient([
        UpsertProfileRequest::class => MockResponse::make([], 200),
        SubscribeProfilesRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoProfileService($klaviyo))->subscribe(
        email: 'a@example.com',
        subscriptionMode: MarketingSubscriptionMode::CustomerRegistration,
        context: ['consented_at' => $consentedAt],
    );

    $expected = $consentedAt->utc()->format('Y-m-d\TH:i:s\Z');

    $mockClient->assertSent(function (SubscribeProfilesRequest $request) use ($expected) {
        $body = $request->body()->all();
        $attributes = $body['data']['attributes'] ?? [];
        $marketing = $attributes['profiles']['data'][0]['attributes']['subscriptions']['email']['marketing'] ?? [];

        return ($attributes['historical_import'] ?? false) === true
            && ($marketing['consent'] ?? null) === 'SUBSCRIBED'
            && ($marketing['consented_at'] ?? null) === $expected;
    });
});

test('subscribe CustomerRegistration clamps near-now consented_at for Klaviyo historical_import', function () {
    $mockClient = new MockClient([
        UpsertProfileRequest::class => MockResponse::make([], 200),
        SubscribeProfilesRequest::class => MockResponse::make([], 202),
    ]);

    $klaviyo = new KlaviyoService;
    $klaviyo->getConnector()->withMockClient($mockClient);

    (new KlaviyoProfileService($klaviyo))->subscribe(
        email: 'a@example.com',
        subscriptionMode: MarketingSubscriptionMode::CustomerRegistration,
        context: ['consented_at' => now()],
    );

    $mockClient->assertSent(function (SubscribeProfilesRequest $request) {
        $body = $request->body()->all();
        $consentedAt = $body['data']['attributes']['profiles']['data'][0]['attributes']['subscriptions']['email']['marketing']['consented_at'] ?? null;

        if (! is_string($consentedAt)) {
            return false;
        }

        $parsed = \Illuminate\Support\Carbon::parse($consentedAt);

        return $parsed->lte(now()->subMinutes(5))
            && str_ends_with($consentedAt, 'Z');
    });
});
