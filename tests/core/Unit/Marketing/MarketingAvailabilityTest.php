<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\Marketing\MarketingAvailability;

beforeEach(function () {
    Config::set('lunar.mailchimp.enabled', false);
    Config::set('lunar.klaviyo.enabled', false);
});

test('newsletterSubscriptionAvailable is false when no providers are enabled', function () {
    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeFalse();
});

test('newsletterSubscriptionAvailable is true when mailchimp is enabled', function () {
    Config::set('lunar.mailchimp.enabled', true);

    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeTrue();
});

test('newsletterSubscriptionAvailable is true when klaviyo is enabled', function () {
    Config::set('lunar.klaviyo.enabled', true);

    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeTrue();
});

test('newsletterSubscriptionAvailable is true when both providers are enabled', function () {
    Config::set('lunar.mailchimp.enabled', true);
    Config::set('lunar.klaviyo.enabled', true);

    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeTrue();
});
