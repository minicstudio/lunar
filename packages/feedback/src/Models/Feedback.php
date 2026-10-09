<?php

namespace Lunar\Feedback\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Base\BaseModel;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property ?int $order_id
 * @property ?int $user_id
 * @property string $type
 * @property int $score
 * @property ?string $comment
 * @property string $locale
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class Feedback extends BaseModel
{
    /**
     * The table associated with the model (prefixed by BaseModel).
     *
     * @var string
     */
    protected $table = 'feedback';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'order_id',
        'user_id',
        'type',
        'score',
        'comment',
        'locale',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'user_id' => 'integer',
            'score' => 'integer',
        ];
    }

    /**
     * The order this feedback rates, if any.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::modelClass());
    }

    /**
     * The authenticated user who left the feedback, if any.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Whether a feedback row already exists for the order and type.
     *
     * @param  int  $orderId  The order primary key.
     * @param  string  $type  The flow key (e.g. checkout.shopping_experience).
     */
    public static function existsFor(int $orderId, string $type): bool
    {
        if ($orderId < 1 || blank($type)) {
            return false;
        }

        return static::query()
            ->where('order_id', $orderId)
            ->where('type', $type)
            ->exists();
    }
}
