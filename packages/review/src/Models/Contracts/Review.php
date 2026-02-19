<?php

namespace Lunar\Review\Models\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface Review
{
    /**
     * Get the associated order.
     */
    public function order(): BelongsTo;

    /**
     * Get the associated user.
     */
    public function user(): BelongsTo;

    /**
     * Get the parent model
     */
    public function reviewable(): MorphTo;

    /**
     * Scope a query to only include approved reviews.
     */
    public function scopeApproved(Builder $query): Builder;

    /**
     * Scope a query to only include reviews for product variants.
     */
    public function scopeForProductVariant(Builder $query, ?int $id = null): Builder;

    /**
     * Scope a query to only include reviews for channels.
     */
    public function scopeForChannel(Builder $query, ?int $id = null): Builder;
}
