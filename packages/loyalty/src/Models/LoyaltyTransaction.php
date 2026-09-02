<?php

namespace Lunar\Loyalty\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lunar\Base\BaseModel;
use Lunar\Loyalty\Database\Factories\LoyaltyTransactionFactory;
use Lunar\Loyalty\Enums\LoyaltyTransactionType;

/**
 * @property int $id
 * @property int $loyalty_account_id
 * @property LoyaltyTransactionType $type
 * @property int $points
 * @property ?int $remaining_points
 * @property string $event_key
 * @property ?string $reference_type
 * @property ?int $reference_id
 * @property ?array $meta
 * @property ?\Illuminate\Support\Carbon $expires_at
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class LoyaltyTransaction extends BaseModel implements Contracts\LoyaltyTransaction
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'loyalty_account_id',
        'type',
        'points',
        'remaining_points',
        'event_key',
        'reference_type',
        'reference_id',
        'meta',
        'expires_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => LoyaltyTransactionType::class,
        'meta' => AsArrayObject::class,
        'expires_at' => 'datetime',
    ];

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): LoyaltyTransactionFactory
    {
        return LoyaltyTransactionFactory::new();
    }

    /**
     * {@inheritDoc}
     */
    public function loyaltyAccount(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::modelClass());
    }

    /**
     * {@inheritDoc}
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
