<?php

namespace Lunar\Base\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Lunar\Facades\Pricing;
use Lunar\Managers\PricingManager;
use Lunar\Models\Currency;
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
     * Resolve a single canonical base price for admin/display use.
     *
     * Prefers the given (or default) currency, then the highest id when
     * duplicate base prices exist for that currency.
     */
    public function canonicalBasePrice(?Currency $currency = null): ?Price
    {
        $currency ??= Currency::getDefault();

        // Reuse an already-eager-loaded relation to avoid an extra query.
        $basePrices = $this->relationLoaded('basePrices')
            ? $this->getRelation('basePrices')
            : $this->basePrices()->get();

        // Prefer the requested/default currency; if none exist for it, fall
        // back to any base price. Highest id wins when duplicates are present.
        return $basePrices
            ->when(
                $currency,
                fn ($prices) => $prices->where('currency_id', $currency->id)->whenEmpty(fn () => $prices)
            )
            ->sortByDesc('id')
            ->first();
    }

    /**
     * Remove duplicate base prices for a currency, keeping one row.
     */
    public function deduplicateBasePricesForCurrency(int $currencyId, ?int $keepId = null): void
    {
        // Default to the newest row so callers can heal orphan duplicates
        // without knowing which id the admin UI is currently editing.
        $keepId ??= $this->basePrices()
            ->where('currency_id', $currencyId)
            ->orderByDesc('id')
            ->value('id');

        if (! $keepId) {
            return;
        }

        $this->basePrices()
            ->where('currency_id', $currencyId)
            ->where('id', '!=', $keepId)
            ->delete();
    }

    /**
     * Return a PricingManager for this model.
     */
    public function pricing(): PricingManager
    {
        return Pricing::for($this);
    }
}
