<?php

namespace Lunar\Review\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lunar\Review\Models\Review;

trait HasReviews
{
    /**
     * Get all reviews associated with the reviewable model.
     */
    public function reviews()
    {
        return function (): MorphMany {
            /** @var \Illuminate\Database\Eloquent\Model $this */
            return $this->morphMany(Review::class, 'reviewable');
        };
    }

    /**
     * Get the average rating of the reviewable model.
     */
    public function getRatingAverage()
    {
        return function (): float {
            /** @var \Illuminate\Database\Eloquent\Model $this */
            return $this->reviews()
                ->approved()
                ->get()
                ->map(fn(Review $review) => (int) $review->attr('rating'))
                ->avg() ?? 0.0;
        };
    }

    /**
     * Get the total number of reviews for the reviewable model.
     */
    public function getTotalReviews()
    {
        return function (): int {
            /** @var \Illuminate\Database\Eloquent\Model $this */
            return $this->reviews()
                ->approved()
                ->count() ?? 0;
        };
    }

    /**
     * Returns the translated name of the reviewable item
     *
     * This method should return the human-readable, translated name of the reviewable item.
     */
    public function getName()
    {
        return function (): string {
            /** @var \Illuminate\Database\Eloquent\Model $this */
            return $this->translateAttribute('name');
        };
    }
}
