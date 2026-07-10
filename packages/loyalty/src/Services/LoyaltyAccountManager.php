<?php

namespace Lunar\Loyalty\Services;

use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Models\Contracts\Customer as CustomerContract;
use Lunar\Models\Customer;

final class LoyaltyAccountManager
{
    /**
     * Find or create a loyalty account for the given customer.
     */
    public function firstOrCreateForCustomer(CustomerContract|Customer $customer): LoyaltyAccount
    {
        return LoyaltyAccount::query()->firstOrCreate([
            'customer_id' => $customer->id,
        ]);
    }

    /**
     * Find a loyalty account for the given customer.
     */
    public function findForCustomer(CustomerContract|Customer $customer): ?LoyaltyAccount
    {
        return LoyaltyAccount::query()->where('customer_id', $customer->id)->first();
    }
}
