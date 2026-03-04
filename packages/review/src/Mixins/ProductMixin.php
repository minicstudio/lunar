<?php

namespace Lunar\Review\Mixins;

class ProductMixin
{
    /**
     * Get the average rating of all product variants.
     *
     * @return float The average rating across all variants, or 0.0 if no ratings exist.
     */
    public function getRatingAverage()
    {
        return function (): float {
            /** @var \Lunar\Models\Product $this */
            return $this->variants
                ->flatMap(function ($variant) {
                    return $variant->reviews()
                        ->approved()
                        ->get()
                        ->map(fn ($review) => (int) $review->attr('rating'));
                })
                ->avg() ?? 0.0;
        };
    }

    /**
     * Get the total number of approved reviews for all variants of the product.
     *
     * @return int The total number of approved reviews across all variants.
     */
    public function getTotalReviews()
    {
        return function (): int {
            /** @var \Lunar\Models\Product $this */
            return $this->variants
                ->sum(fn ($variant) => $variant->getTotalReviews());
        };
    }
}
