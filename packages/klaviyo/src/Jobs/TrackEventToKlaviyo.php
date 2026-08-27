<?php

namespace Lunar\Klaviyo\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Services\KlaviyoProfileService;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class TrackEventToKlaviyo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $email,
        public string $eventName,
        public array $properties = [],
        public string $eventId = '',
    ) {
        $this->tries = config('lunar.klaviyo.retry.max_attempts', 4);
        $this->backoff = config('lunar.klaviyo.retry.backoff', [60, 300, 3600]);

        // Generate once at construction when producer did not supply an id.
        // Serialized onto the job so retries reuse the same value.
        if ($this->eventId === '') {
            $this->eventId = (string) \Illuminate\Support\Str::uuid();
        }
    }

    public function handle(): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.track_events', true)) {
            KlaviyoLogger::debug('Track event job skipped — enabled or track_events off', [
                'email' => $this->email,
                'event_name' => $this->eventName,
                'event_id' => $this->eventId,
                'enabled' => (bool) config('lunar.klaviyo.enabled', false),
                'track_events' => (bool) config('lunar.klaviyo.track_events', true),
            ]);

            return;
        }

        KlaviyoLogger::debug('Track event job started', [
            'email' => $this->email,
            'event_name' => $this->eventName,
            'event_id' => $this->eventId,
            'property_keys' => array_keys($this->properties),
            'attempt' => $this->attempts(),
        ]);

        try {
            $properties = app(KlaviyoCatalogService::class)
                ->mapEventProductIdentifiers($this->properties);

            app(KlaviyoProfileService::class)->trackEvent(
                email: $this->email,
                eventName: $this->eventName,
                properties: $properties,
                eventId: $this->eventId,
            );

            KlaviyoLogger::debug('Track event job completed', [
                'email' => $this->email,
                'event_name' => $this->eventName,
                'event_id' => $this->eventId,
            ]);
        } catch (Exception $e) {
            KlaviyoLogger::error('Track event job failed', [
                'email' => $this->email,
                'event_name' => $this->eventName,
                'event_id' => $this->eventId,
                'attempt' => $this->attempts(),
            ], $e);

            throw new FailedKlaviyoSyncException(
                "Klaviyo track event error for '{$this->eventName}' (eventId: {$this->eventId}). ".$e->getMessage()
            );
        }
    }
}
