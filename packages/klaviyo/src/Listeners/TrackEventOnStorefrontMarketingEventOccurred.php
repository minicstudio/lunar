<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Klaviyo\Jobs\TrackEventToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class TrackEventOnStorefrontMarketingEventOccurred
{
    public function handle(StorefrontMarketingEventOccurred $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.track_events', true)) {
            KlaviyoLogger::debug('Storefront listener skipped — enabled or track_events off', [
                'email' => $event->email,
                'event_name' => $event->eventName,
                'event_id' => $event->eventId,
                'enabled' => (bool) config('lunar.klaviyo.enabled', false),
                'track_events' => (bool) config('lunar.klaviyo.track_events', true),
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
