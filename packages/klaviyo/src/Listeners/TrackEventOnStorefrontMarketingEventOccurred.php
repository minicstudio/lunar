<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Klaviyo\Jobs\TrackEventToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class TrackEventOnStorefrontMarketingEventOccurred
{
    public function handle(StorefrontMarketingEventOccurred $event): void
    {
        if (! KlaviyoAvailability::eventTrackingEnabled()) {
            KlaviyoLogger::debug('Storefront listener skipped — enabled or track_events off', [
                'email' => $event->email,
                'event_name' => $event->eventName,
                'event_id' => $event->eventId,
                'enabled' => KlaviyoAvailability::enabled(),
                'track_events' => KlaviyoAvailability::trackEvents(),
            ]);

            return;
        }

        KlaviyoLogger::debug('Storefront listener dispatching TrackEventToKlaviyo', [
            'email' => $event->email,
            'event_name' => $event->eventName,
            'event_id' => $event->eventId,
        ]);

        dispatch(new TrackEventToKlaviyo(
            email: $event->email,
            eventName: $event->eventName,
            properties: $event->properties,
            eventId: $event->eventId,
        ))->onConnection(config('lunar.klaviyo.queue_connection', 'deferred'));
    }
}
