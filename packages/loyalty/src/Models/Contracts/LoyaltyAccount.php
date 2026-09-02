<?php

namespace Lunar\Loyalty\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface LoyaltyAccount
{
    /**
     * Return the customer relationship.
     */
    public function customer(): BelongsTo;

    /**
     * Return the transactions relationship.
     */
    public function transactions(): HasMany;

    /**
     * Return the earn transactions relationship.
     */
    public function earnTransactions(): HasMany;

    /**
     * Return the cached balance for display purposes.
     */
    public function getBalanceForDisplay(): int;

    /**
     * Return the available balance for spending authorization.
     */
    public function getAvailableBalance(): int;

    /**
     * @return int Cached balance for display (accessor: display_balance).
     */
    public function getDisplayBalanceAttribute(): int;

    /**
     * @return int Spendable balance from lots (accessor: available_balance).
     */
    public function getAvailableBalanceAttribute(): int;
}
