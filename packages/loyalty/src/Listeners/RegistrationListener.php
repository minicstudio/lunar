<?php

namespace Lunar\Loyalty\Listeners;

use Lunar\Loyalty\Services\LoyaltyEngine;
use Lunar\Models\Customer;

final class RegistrationListener
{
    public function __construct(
        protected LoyaltyEngine $engine,
    ) {}

    /**
     * Handle customer registration for loyalty bonus.
     */
    public function handle(Customer $customer): void
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return;
        }

        if (! config('lunar.loyalty.events.registration')) {
            return;
        }

        $this->engine->earnFromRegistration($customer);
    }
}
