<?php

namespace Lunar\Events\Marketing;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Enums\Marketing\MarketingConsentSource;
use Lunar\Enums\Marketing\MarketingSubscriptionMode;
use Lunar\Models\Customer;

class CustomerMarketingConsentGranted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $context  Provider-neutral extras only (e.g. locale).
     */
    public function __construct(
        public string $email,
        public MarketingConsentSource $source,
        public MarketingSubscriptionMode $subscriptionMode,
        public ?Customer $customer = null,
        public array $context = [],
    ) {}
}
