<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Lunar\Enums\Marketing\MarketingConsentSource;
use Lunar\Enums\Marketing\MarketingSubscriptionMode;
use Lunar\Events\Marketing\CustomerMarketingConsentGranted;
use Lunar\Events\Marketing\CustomerMarketingProfileUpdated;
use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Models\Customer;

test('MarketingConsentSource cases use expected string values', function () {
    expect(MarketingConsentSource::Registration->value)->toBe('registration')
        ->and(MarketingConsentSource::OAuth->value)->toBe('oauth')
        ->and(MarketingConsentSource::Newsletter->value)->toBe('newsletter')
        ->and(MarketingConsentSource::Checkout->value)->toBe('checkout')
        ->and(MarketingConsentSource::Order->value)->toBe('order');
});

test('MarketingSubscriptionMode cases use expected string values', function () {
    expect(MarketingSubscriptionMode::CustomerRegistration->value)->toBe('customer_registration')
        ->and(MarketingSubscriptionMode::ExplicitOptIn->value)->toBe('explicit_opt_in');
});

test('CustomerMarketingConsentGranted stores payload fields', function () {
    $customer = Mockery::mock(Customer::class);

    $event = new CustomerMarketingConsentGranted(
        email: 'a@example.com',
        source: MarketingConsentSource::Registration,
        subscriptionMode: MarketingSubscriptionMode::CustomerRegistration,
        customer: $customer,
        context: ['locale' => 'hu'],
    );

    expect($event->email)->toBe('a@example.com')
        ->and($event->source)->toBe(MarketingConsentSource::Registration)
        ->and($event->subscriptionMode)->toBe(MarketingSubscriptionMode::CustomerRegistration)
        ->and($event->customer)->toBe($customer)
        ->and($event->context)->toBe(['locale' => 'hu']);
});

test('CustomerMarketingProfileUpdated stores properties', function () {
    $customer = Mockery::mock(Customer::class);

    $event = new CustomerMarketingProfileUpdated(
        customer: $customer,
        properties: ['language' => 'hu'],
    );

    expect($event->customer)->toBe($customer)
        ->and($event->properties)->toBe(['language' => 'hu']);
});

test('StorefrontMarketingEventOccurred uses uniqueKey as eventId when provided', function () {
    $event = new StorefrontMarketingEventOccurred(
        email: 'a@example.com',
        eventName: 'begin_checkout',
        properties: ['cart_id' => '9'],
        uniqueKey: 'begin_checkout:cart:9',
    );

    expect($event->eventId)->toBe('begin_checkout:cart:9')
        ->and($event->email)->toBe('a@example.com')
        ->and($event->eventName)->toBe('begin_checkout');
});

test('StorefrontMarketingEventOccurred generates eventId once when uniqueKey is absent', function () {
    $event = new StorefrontMarketingEventOccurred(
        email: 'a@example.com',
        eventName: 'view_item',
    );

    $first = $event->eventId;
    $second = $event->eventId;

    expect($first)->not->toBeEmpty()
        ->and($second)->toBe($first);
});
