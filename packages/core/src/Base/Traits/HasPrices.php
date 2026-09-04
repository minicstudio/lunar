<?php

namespace Lunar\Base\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lunar\Facades\Pricing;
use Lunar\Managers\PricingManager;
use Lunar\Models\Price;

trait HasPrices
{
    /**
     * Get all of the models prices.
     *
     * @return MorphMany<Price, $this>
     */
    public function prices(): MorphMany
    {
        return $this->morphMany(
            Price::modelClass(),
            'priceable'
        );
    }

    /**
     * Return base prices query.
     *
     * @return MorphMany<Price, $this>
     */
    public function basePrices(): MorphMany
    {
        return $this->prices()->whereMinQuantity(1)->whereNull('customer_group_id');
    }

    /**
     * @return MorphMany<Price, $this>
     */
    public function priceBreaks(): MorphMany
    {
        return $this->prices()->where('min_quantity', '>', 1);
    }

    /**
     * Return a PricingManager for this model.
     */
    public function pricing(): PricingManager
    {
        return Pricing::for($this);
    }
}
