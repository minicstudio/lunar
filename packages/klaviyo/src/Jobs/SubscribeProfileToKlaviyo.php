<?php

namespace Lunar\Klaviyo\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Enums\Marketing\MarketingSubscriptionMode;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoProfileService;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Customer;

class SubscribeProfileToKlaviyo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $email,
        public MarketingSubscriptionMode $subscriptionMode,
        public ?Customer $customer = null,
        public array $context = [],
    ) {
        $this->tries = config('lunar.klaviyo.retry.max_attempts', 4);
        $this->backoff = config('lunar.klaviyo.retry.backoff', [60, 300, 3600]);
    }

    public function handle(): void
    {
        if (! config('lunar.klaviyo.enabled', false)) {
            KlaviyoLogger::debug('Subscribe job skipped — klaviyo disabled', [
                'email' => $this->email,
            ]);

            return;
        }

        KlaviyoLogger::debug('Subscribe job started', [
            'email' => $this->email,
            'subscription_mode' => $this->subscriptionMode->value,
            'customer_id' => $this->customer?->id,
            'attempt' => $this->attempts(),
        ]);

        try {
            app(KlaviyoProfileService::class)->subscribe(
                email: $this->email,
                subscriptionMode: $this->subscriptionMode,
                context: $this->context,
                customer: $this->customer,
            );

            KlaviyoLogger::debug('Subscribe job completed', [
                'email' => $this->email,
                'subscription_mode' => $this->subscriptionMode->value,
            ]);
        } catch (Exception $e) {
            KlaviyoLogger::error('Subscribe job failed', [
                'email' => $this->email,
                'subscription_mode' => $this->subscriptionMode->value,
                'attempt' => $this->attempts(),
            ], $e);

            throw new FailedKlaviyoSyncException(
                'Klaviyo subscribe error for '.$this->email.'. '.$e->getMessage()
            );
        }
    }
}
