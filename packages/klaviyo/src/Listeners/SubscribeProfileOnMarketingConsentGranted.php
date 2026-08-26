<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Events\Marketing\CustomerMarketingConsentGranted;
use Lunar\Klaviyo\Jobs\SubscribeProfileToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SubscribeProfileOnMarketingConsentGranted
{
    public function handle(CustomerMarketingConsentGranted $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)) {
            KlaviyoLogger::debug('Consent listener skipped — klaviyo disabled', [
                'email' => $event->email,
                'source' => $event->source->value,
            ]);

            return;
        }

        KlaviyoLogger::debug('Consent listener dispatching SubscribeProfileToKlaviyo', [
            'email' => $event->email,
            'source' => $event->source->value,
            'subscription_mode' => $event->subscriptionMode->value,
            'customer_id' => $event->customer?->id,
        ]);

        SubscribeProfileToKlaviyo::dispatch(
            email: $event->email,
            subscriptionMode: $event->subscriptionMode,
            customer: $event->customer,
            context: $event->context,
        );
    }
}
