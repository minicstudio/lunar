<?php

namespace Lunar\Loyalty\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Base\BaseModel;
use Lunar\Loyalty\Database\Factories\LoyaltyAccountFactory;
use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Models\Customer;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $balance
 * @property int $lifetime_earned
 * @property int $lifetime_spent
 * @property-read int $display_balance
 * @property-read int $available_balance
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class LoyaltyAccount extends BaseModel implements Contracts\LoyaltyAccount
{
    use HasFactory;

    /**
     * Only customer_id is mass-assignable; balance and lifetime counters must be
     * written exclusively through LoyaltyLedger to preserve ledger integrity.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'customer_id',
    ];

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): LoyaltyAccountFactory
    {
        return LoyaltyAccountFactory::new();
    }

    /**
     * {@inheritDoc}
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::modelClass());
    }

    /**
     * {@inheritDoc}
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::modelClass())->orderBy('created_at', 'desc');
    }

    /**
     * {@inheritDoc}
     */
    public function earnTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::modelClass())
            ->where('type', LoyaltyTransactionType::Earn->value);
    }

    /**
     * {@inheritDoc}
     */
    public function getBalanceForDisplay(): int
    {
        return $this->balance;
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableBalance(): int
    {
        return (int) $this->earnTransactions()
            ->where('remaining_points', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('remaining_points');
    }

    /**
     * Cached balance for display (storefront, admin UI).
     */
    public function getDisplayBalanceAttribute(): int
    {
        return $this->getBalanceForDisplay();
    }

    /**
     * Spendable balance from non-expired earn lots (checkout authorization).
     */
    public function getAvailableBalanceAttribute(): int
    {
        return $this->getAvailableBalance();
    }
}
