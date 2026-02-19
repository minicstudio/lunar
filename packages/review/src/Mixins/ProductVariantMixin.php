<?php

namespace Lunar\Review\Mixins;

use Lunar\Review\Traits\HasReviews;

class ProductVariantMixin
{
    use HasReviews;

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
