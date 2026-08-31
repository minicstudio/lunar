<?php

namespace Lunar\Klaviyo\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoProfileService;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Customer;

class SyncProfileToKlaviyo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public Customer $customer,
        public array $properties = [],
    ) {
        $this->tries = config('lunar.klaviyo.retry.max_attempts', 4);
        $this->backoff = config('lunar.klaviyo.retry.backoff', [60, 300, 3600]);
    }

    public function handle(): void
    {
        if (! KlaviyoAvailability::subscriberSyncEnabled()) {
            KlaviyoLogger::debug('Profile sync job skipped — enabled or sync_subscribers off', [
                'customer_id' => $this->customer->id,
                'enabled' => KlaviyoAvailability::enabled(),
                'sync_subscribers' => KlaviyoAvailability::syncSubscribers(),
            ]);

            return;
        }

        $user = $this->customer->users()?->first();

        if (! $user?->email) {
            KlaviyoLogger::error('Profile sync job failed — missing user email', [
                'customer_id' => $this->customer->id,
            ]);

            throw new FailedKlaviyoSyncException(
                "Customer {$this->customer->id} has no associated user email for Klaviyo profile sync."
            );
        }

        KlaviyoLogger::debug('Profile sync job started', [
            'customer_id' => $this->customer->id,
            'email' => $user->email,
            'properties' => array_keys($this->properties),
            'attempt' => $this->attempts(),
        ]);

        try {
            $properties = $this->properties;

            if (! array_key_exists('language', $properties) && filled($user->locale ?? null)) {
                $properties['language'] = $user->locale;
            }

            $attributes = app(KlaviyoProfileService::class)->mapProfileAttributes($properties);

            if ($user->first_name) {
                $attributes['first_name'] = $user->first_name;
            }

            if ($user->last_name) {
                $attributes['last_name'] = $user->last_name;
            }

            app(KlaviyoProfileService::class)->upsertProfile($user->email, $attributes);

            KlaviyoLogger::debug('Profile sync job completed', [
                'customer_id' => $this->customer->id,
                'email' => $user->email,
                'attribute_keys' => array_keys($attributes),
            ]);
        } catch (Exception $e) {
            KlaviyoLogger::error('Profile sync job failed', [
                'customer_id' => $this->customer->id,
                'email' => $user->email,
                'attempt' => $this->attempts(),
            ], $e);

            throw new FailedKlaviyoSyncException(
                'Klaviyo profile sync error for customer '.$this->customer->id.'. '.$e->getMessage()
            );
        }
    }
}
