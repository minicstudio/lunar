<?php

namespace Lunar\Review\Mixins;

use Lunar\Review\Models\Review;

class ProductMixin
{
    /**
     * Get the average rating of all product variants.
     *
     * Uses eager-loaded reviews when available to avoid N+1 queries.
     *
     * @return float The average rating across all variants, or 0.0 if no ratings exist.
     */
    public function getRatingAverage()
    {
        return function (): float {
            /** @var \Lunar\Models\Product $this */
            $ratings = $this->variants->flatMap(function ($variant) {
                $reviews = $variant->relationLoaded('reviews')
                    ? $variant->reviews->filter(fn (Review $review) => $review->approved_at !== null)
                    : $variant->reviews()->approved()->get();

                return $reviews->map(fn (Review $review) => (int) $review->attr('rating'));
            });

            return (float) ($ratings->avg() ?? 0.0);
        };
    }

    /**
     * Get the total number of approved reviews for all variants of the product.
     *
     * Uses eager-loaded reviews when available to avoid N+1 queries.
     *
     * @return int The total number of approved reviews across all variants.
     */
    public function getTotalReviews()
    {
        return function (): int {
            /** @var \Lunar\Models\Product $this */
            return (int) $this->variants->sum(function ($variant) {
                if ($variant->relationLoaded('reviews')) {
                    return $variant->reviews
                        ->filter(fn (Review $review) => $review->approved_at !== null)
                        ->count();
                }

                return $variant->getTotalReviews();
            });
        };
    }
}
