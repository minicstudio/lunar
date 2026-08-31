<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\Klaviyo\Support\KlaviyoAvailability;

beforeEach(function () {
    Config::set('lunar.klaviyo.enabled', false);
    Config::set('lunar.klaviyo.sync_products', false);
    Config::set('lunar.klaviyo.sync_orders', false);
    Config::set('lunar.klaviyo.sync_subscribers', false);
    Config::set('lunar.klaviyo.track_events', true);
});

test('enabled reflects klaviyo.enabled config', function () {
    expect(KlaviyoAvailability::enabled())->toBeFalse();

    Config::set('lunar.klaviyo.enabled', true);

    expect(KlaviyoAvailability::enabled())->toBeTrue();
});

test('catalogSyncEnabled requires enabled and sync_products', function () {
    Config::set('lunar.klaviyo.enabled', true);

    expect(KlaviyoAvailability::catalogSyncEnabled())->toBeFalse();

    Config::set('lunar.klaviyo.sync_products', true);

    expect(KlaviyoAvailability::catalogSyncEnabled())->toBeTrue();
});

test('orderSyncEnabled requires enabled and sync_orders', function () {
    Config::set('lunar.klaviyo.enabled', true);
    Config::set('lunar.klaviyo.sync_orders', true);

    expect(KlaviyoAvailability::orderSyncEnabled())->toBeTrue();
});

test('subscriberSyncEnabled requires enabled and sync_subscribers', function () {
    Config::set('lunar.klaviyo.enabled', true);
    Config::set('lunar.klaviyo.sync_subscribers', true);

    expect(KlaviyoAvailability::subscriberSyncEnabled())->toBeTrue();
});

test('eventTrackingEnabled requires enabled and track_events', function () {
    Config::set('lunar.klaviyo.enabled', true);

    expect(KlaviyoAvailability::eventTrackingEnabled())->toBeTrue();

    Config::set('lunar.klaviyo.track_events', false);

    expect(KlaviyoAvailability::eventTrackingEnabled())->toBeFalse();
});
