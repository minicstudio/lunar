<?php

namespace Lunar\Loyalty\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface LoyaltyTransaction
{
    /**
     * Return the loyalty account relationship.
     */
    public function loyaltyAccount(): BelongsTo;

    /**
     * Return the reference relationship.
     */
    public function reference(): MorphTo;
}
