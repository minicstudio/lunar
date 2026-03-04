<?php

namespace Lunar\Review\Mixins;

use Illuminate\Support\Collection;
use Lunar\Models\Order;
use Lunar\Review\Traits\HasReviews;

class ProductVariantMixin
{
    use HasReviews;

    /**
     * Retrieve the product variants associated with a given order that are eligible for review.
     */
    public function getReviewableProductVariants()
    {
        return function (Order $order): Collection {
            return collect(
                $order->productLines->mapWithKeys(function ($line) {
                    $purchasable = $line->purchasable;
                    $name = $purchasable->getDescription();
                    $option = $purchasable->getOption();

                    $label = $option
                        ? $name.' - '.$option
                        : $name;

                    return [$purchasable->id => $label];
                })
            );
        };
    }

    /**
     * Returns the name of the product variant.
     */
    public function getName()
    {
        return function (): string {
            /** @var \Lunar\Models\ProductVariant $this */
            $option = $this->getOption();

            return $option ? $this->getDescription().' - '.$option : $this->getDescription();
        };
    }
}
