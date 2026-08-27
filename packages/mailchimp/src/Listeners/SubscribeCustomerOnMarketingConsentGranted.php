<?php

namespace Lunar\Mailchimp\Listeners;

use Lunar\Enums\Marketing\MarketingSubscriptionMode;
use Lunar\Events\Marketing\CustomerMarketingConsentGranted;
use Lunar\Mailchimp\Jobs\SubscribeEmailToMailchimp;
use Lunar\Mailchimp\Jobs\SyncSubscriberToMailchimp;

class SubscribeCustomerOnMarketingConsentGranted
{
    public function handle(CustomerMarketingConsentGranted $event): void
    {
        if (! config('lunar.mailchimp.enabled', false)) {
            return;
        }

        match ($event->subscriptionMode) {
            MarketingSubscriptionMode::CustomerRegistration => $this->dispatchCustomerRegistration($event),
            MarketingSubscriptionMode::ExplicitOptIn => SubscribeEmailToMailchimp::dispatch($event->email)
                ->onConnection(config('lunar.mailchimp.queue_connection', 'deferred')),
        };
    }

    protected function dispatchCustomerRegistration(CustomerMarketingConsentGranted $event): void
    {
        if (! $event->customer) {
            return;
        }

        SyncSubscriberToMailchimp::dispatch($event->customer)
            ->onConnection(config('lunar.mailchimp.queue_connection', 'deferred'));
    }
}
