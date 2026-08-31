<?php

namespace Lunar\Mailchimp\Listeners;

use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Exceptions\SilentException;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;

class TrackEventOnStorefrontMarketingEventOccurred
{
    public function handle(StorefrontMarketingEventOccurred $event): void
    {
        if (! config('lunar.mailchimp.enabled', false)
            || ! config('lunar.mailchimp.track_events', true)) {
            return;
        }

        try {
            app(MailchimpSubscriberService::class)->trackEvent(
                $event->email,
                $event->eventName,
                $this->flattenProperties($event->properties),
            );
        } catch (\Exception $e) {
            report(new SilentException(
                "Failed to track '{$event->eventName}' to Mailchimp (eventId: {$event->eventId}). Error: ".$e->getMessage()
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, string>
     */
    protected function flattenProperties(array $properties): array
    {
        $flattened = [];

        foreach ($properties as $key => $value) {
            if (is_array($value)) {
                $flattened[$key] = json_encode($value);

                continue;
            }

            $flattened[$key] = (string) $value;
        }

        return $flattened;
    }
}
