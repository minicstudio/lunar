<?php

namespace Lunar\Review\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lunar\Base\BaseModel;
use Lunar\Base\Casts\AsAttributeData;
use Lunar\Base\Traits\HasMedia;
use Lunar\Base\Traits\HasTranslations;
use Lunar\Models\Channel;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Lunar\Review\Database\Factories\ReviewFactory;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

class Review extends BaseModel implements SpatieHasMedia, Contracts\Review
{
    use HasFactory, HasMedia, HasTranslations, SoftDeletes;

    /**
     * Fillable fields
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'user_id',
        'reviewable_id',
        'reviewable_type',
        'attribute_data',
        'approved_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'attribute_data' => AsAttributeData::class,
        'approved_at' => 'datetime',
    ];

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ReviewFactory::new();
    }

    /**
     * Get the associated order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the associated user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the parent model
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include approved reviews.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    /**
     * Scope a query to only include reviews for product variants.
     */
    public function scopeForProductVariant(Builder $query, ?int $id = null): Builder
    {
        return $query->where('reviewable_type', ProductVariant::morphName())
            ->when($id, function ($query) use ($id) {
                return $query->where('reviewable_id', $id);
            });
    }

    /**
     * Scope a query to only include reviews for channels.
     */
    public function scopeForChannel(Builder $query, ?int $id = null): Builder
    {
        return $query->where('reviewable_type', Channel::morphName())
            ->when($id, function ($query) use ($id) {
                return $query->where('reviewable_id', $id);
            });
    }
}
