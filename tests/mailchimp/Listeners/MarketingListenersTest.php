<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Enums\Marketing\MarketingConsentSource;
use Lunar\Enums\Marketing\MarketingSubscriptionMode;
use Lunar\Events\Marketing\CustomerMarketingConsentGranted;
use Lunar\Events\Marketing\CustomerMarketingProfileUpdated;
use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Mailchimp\Jobs\SubscribeEmailToMailchimp;
use Lunar\Mailchimp\Jobs\SyncSubscriberToMailchimp;
use Lunar\Mailchimp\Listeners\SubscribeCustomerOnMarketingConsentGranted;
use Lunar\Mailchimp\Listeners\SyncCustomerOnMarketingProfileUpdated;
use Lunar\Mailchimp\Listeners\TrackEventOnStorefrontMarketingEventOccurred;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;
use Lunar\Models\Customer;

beforeEach(function () {
    Queue::fake();

    Config::set('lunar.mailchimp.enabled', true);
    Config::set('lunar.mailchimp.track_events', true);
    Config::set('lunar.mailchimp.merge_fields.language', 'LANGUAGE');
});

test('consent listener dispatches SyncSubscriberToMailchimp for CustomerRegistration', function () {
    $customer = Mockery::mock(Customer::class);

    $event = new CustomerMarketingConsentGranted(
        email: 'a@example.com',
        source: MarketingConsentSource::Registration,
        subscriptionMode: MarketingSubscriptionMode::CustomerRegistration,
        customer: $customer,
    );

    (new SubscribeCustomerOnMarketingConsentGranted)->handle($event);

    Queue::assertPushed(SyncSubscriberToMailchimp::class);
    Queue::assertNotPushed(SubscribeEmailToMailchimp::class);
});

test('consent listener dispatches SubscribeEmailToMailchimp for ExplicitOptIn', function () {
    $event = new CustomerMarketingConsentGranted(
        email: 'optin@example.com',
        source: MarketingConsentSource::Newsletter,
        subscriptionMode: MarketingSubscriptionMode::ExplicitOptIn,
    );

    (new SubscribeCustomerOnMarketingConsentGranted)->handle($event);

    Queue::assertPushed(SubscribeEmailToMailchimp::class, function (SubscribeEmailToMailchimp $job) {
        return $job->email === 'optin@example.com';
    });
    Queue::assertNotPushed(SyncSubscriberToMailchimp::class);
});

test('consent listener no-ops when mailchimp is disabled', function () {
    Config::set('lunar.mailchimp.enabled', false);

    $event = new CustomerMarketingConsentGranted(
        email: 'a@example.com',
        source: MarketingConsentSource::Newsletter,
        subscriptionMode: MarketingSubscriptionMode::ExplicitOptIn,
    );

    (new SubscribeCustomerOnMarketingConsentGranted)->handle($event);

    Queue::assertNothingPushed();
});

test('profile listener maps language-only to languageOnly job', function () {
    $customer = Mockery::mock(Customer::class);

    $event = new CustomerMarketingProfileUpdated(
        customer: $customer,
        properties: ['language' => 'hu'],
    );

    (new SyncCustomerOnMarketingProfileUpdated)->handle($event);

    Queue::assertPushed(SyncSubscriberToMailchimp::class, function (SyncSubscriberToMailchimp $job) {
        return $job->languageOnly === true;
    });
});

test('storefront listener calls trackEvent when enabled', function () {
    $mock = Mockery::mock(MailchimpSubscriberService::class);
    $mock->shouldReceive('trackEvent')
        ->once()
        ->with('a@example.com', 'begin_checkout', Mockery::type('array'))
        ->andReturn([]);

    app()->instance(MailchimpSubscriberService::class, $mock);

    $event = new StorefrontMarketingEventOccurred(
        email: 'a@example.com',
        eventName: 'begin_checkout',
        properties: ['cart_id' => '1'],
        uniqueKey: 'begin_checkout:cart:1',
    );

    (new TrackEventOnStorefrontMarketingEventOccurred)->handle($event);
});

test('storefront listener no-ops when track_events is disabled', function () {
    Config::set('lunar.mailchimp.track_events', false);

    $mock = Mockery::mock(MailchimpSubscriberService::class);
    $mock->shouldNotReceive('trackEvent');
    app()->instance(MailchimpSubscriberService::class, $mock);

    $event = new StorefrontMarketingEventOccurred(
        email: 'a@example.com',
        eventName: 'begin_checkout',
    );

    (new TrackEventOnStorefrontMarketingEventOccurred)->handle($event);
});
