<?php

namespace Lunar\Loyalty\Mixins;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Lunar\Loyalty\Models\LoyaltyAccount;

class CustomerMixin
{
    /**
     * Return the loyalty account relationship.
     */
    public function loyaltyAccount(): \Closure
    {
        return function (): HasOne {
            /** @var \Lunar\Models\Customer $this */
            return $this->hasOne(LoyaltyAccount::modelClass());
        };
    }
}
