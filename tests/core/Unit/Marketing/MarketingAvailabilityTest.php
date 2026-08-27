<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\Marketing\MarketingAvailability;

beforeEach(function () {
    Config::set('lunar.mailchimp.enabled', false);
    Config::set('lunar.mailchimp.api_key', null);
    Config::set('lunar.mailchimp.list_id', null);
    Config::set('lunar.klaviyo.enabled', false);
    Config::set('lunar.klaviyo.api_key', null);
    Config::set('lunar.klaviyo.list_id', null);
});

test('newsletterSubscriptionAvailable is false when no providers are enabled', function () {
    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeFalse();
});

test('newsletterSubscriptionAvailable is false when mailchimp enabled without credentials', function () {
    Config::set('lunar.mailchimp.enabled', true);

    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeFalse();
});

test('newsletterSubscriptionAvailable is true when mailchimp is enabled with credentials', function () {
    Config::set('lunar.mailchimp.enabled', true);
    Config::set('lunar.mailchimp.api_key', 'key');
    Config::set('lunar.mailchimp.list_id', 'list');

    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeTrue();
});

test('newsletterSubscriptionAvailable is false when klaviyo enabled without credentials', function () {
    Config::set('lunar.klaviyo.enabled', true);

    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeFalse();
});

test('newsletterSubscriptionAvailable is true when klaviyo is enabled with credentials', function () {
    Config::set('lunar.klaviyo.enabled', true);
    Config::set('lunar.klaviyo.api_key', 'pk_test');
    Config::set('lunar.klaviyo.list_id', 'list_doi');

    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeTrue();
});

test('newsletterSubscriptionAvailable is true when both providers are credentialed', function () {
    Config::set('lunar.mailchimp.enabled', true);
    Config::set('lunar.mailchimp.api_key', 'key');
    Config::set('lunar.mailchimp.list_id', 'list');
    Config::set('lunar.klaviyo.enabled', true);
    Config::set('lunar.klaviyo.api_key', 'pk_test');
    Config::set('lunar.klaviyo.list_id', 'list_doi');

    expect(app(MarketingAvailability::class)->newsletterSubscriptionAvailable())->toBeTrue();
});
