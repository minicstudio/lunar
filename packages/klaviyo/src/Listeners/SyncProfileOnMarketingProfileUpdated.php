<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Events\Marketing\CustomerMarketingProfileUpdated;
use Lunar\Klaviyo\Jobs\SyncProfileToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncProfileOnMarketingProfileUpdated
{
    public function handle(CustomerMarketingProfileUpdated $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_subscribers', false)) {
            KlaviyoLogger::debug('Profile listener skipped — enabled or sync_subscribers off', [
                'customer_id' => $event->customer->id,
                'enabled' => (bool) config('lunar.klaviyo.enabled', false),
                'sync_subscribers' => (bool) config('lunar.klaviyo.sync_subscribers', false),
                'properties' => array_keys($event->properties),
            ]);

            return;
        }

        KlaviyoLogger::debug('Profile listener dispatching SyncProfileToKlaviyo', [
            'customer_id' => $event->customer->id,
            'properties' => array_keys($event->properties),
        ]);

        dispatch(new SyncProfileToKlaviyo(
            customer: $event->customer,
            properties: $event->properties,
        ))->onConnection(config('lunar.klaviyo.queue_connection', 'deferred'));
    }
}
