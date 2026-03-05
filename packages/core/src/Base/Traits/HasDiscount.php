<?php

namespace Lunar\Base\Traits;

use Illuminate\Support\Collection;
use Lunar\Base\DiscountManagerInterface;
use Lunar\DataTypes\Price;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Facades\StorefrontSession;
use Lunar\Models\Currency;
use Lunar\Models\TaxZone;

trait HasDiscount
{
    /**
     * Get the discount manager instance.
     * Override this method in your model if you need custom behavior.
     */
    protected function getDiscountManager(): DiscountManagerInterface
    {
        return app(DiscountManagerInterface::class);
    }

    /**
     * Check if the purchasable has a discount.
     */
    public function hasDiscount(): bool
    {
        return $this->getDiscountManager()->getDiscountForPurchasable($this) ? true : false;
    }

    /**
     * Get the original prices for the product. (ex tax)
     */
    public function getOriginalPrices(): Collection
    {
        // Lowest price first
        $prices = $this->prices->sortBy('price');

        return $prices->map(function ($price) {
            return $price->priceExTax();
        });
    }

    /**
     * Get the original prices for the product. (inc tax)
     */
    public function getOriginalPricesIncTax(): Collection
    {
        // Lowest price first
        $prices = $this->prices->sortBy('price');

        return $prices->map(function ($price) {
            return $price->priceIncTax();
        });
    }

    /**
     * Get the discounted prices for the product.
     */
    public function getDiscountedPrices(): Collection
    {
        $discount = $this->getDiscountManager()->getDiscountForPurchasable($this);
        $discountedPrice = collect();

        if (! $discount) {
            return $discountedPrice;
        }

        $originalPrice = $this->getOriginalPrices();

        $originalPrice->each(function ($price) use ($discount, &$discountedPrice) {
            // Call the static method dynamically based on discount type
            $discountedPriceValue = $discount->type::calculateDiscountedPrice($price, $discount->data);

            // Ensure price doesn't go below 0
            $discountedPriceValue = max(0, $discountedPriceValue);

            $discountedPrice->push(new Price(
                $discountedPriceValue,
                $price->currency
            ));
        });

        return $discountedPrice;
    }

    /**
     * Get the discounted prices for the product (inc tax).
     * Calculates discounted prices including tax by applying tax to ex tax discounted prices.
     */
    public function getDiscountedPricesIncTax(): Collection
    {
        $discountedPricesExcTax = $this->getDiscountedPrices();
        $discountedPricesIncTax = collect();

        if ($discountedPricesExcTax->isEmpty()) {
            return $discountedPricesIncTax;
        }

        $taxRate = $this->getTaxRate();

        // Apply tax rate to each discounted ex tax price
        $discountedPricesExcTax->each(function ($priceExcTax) use ($taxRate, &$discountedPricesIncTax) {
            $valueIncTax = (int) ($priceExcTax->value * (1 + $taxRate));

            $discountedPricesIncTax->push(new Price(
                $valueIncTax,
                $priceExcTax->currency
            ));
        });

        return $discountedPricesIncTax;
    }

    /**
     * Resolve the default tax rate for the purchasable.
     */
    public function getTaxRate(): float
    {
        $taxClass = $this->getTaxClass();
        $taxZone = TaxZone::where('default', true)->first();

        if (! $taxClass || ! $taxZone) {
            return 0.0;
        }

        $firstTaxRateAmount = $taxClass->taxRateAmounts()
            ->whereIn('tax_rate_id', $taxZone->taxRates->pluck('id'))
            ->with('taxRate')
            ->get()
            ->sortBy(function ($item) {
                return $item->taxRate->priority;
            })
            ->first();

        if (! $firstTaxRateAmount) {
            return 0.0;
        }

        return (float) ($firstTaxRateAmount->percentage / 100);
    }

    /**
     * Get the current prices for the product (ex tax).
     * Simple prices function is not OK due to the naming conflict
     */
    public function getCurrentPrices(): Collection
    {
        $discountedPrice = $this->getDiscountedPrices();

        return $discountedPrice->isNotEmpty()
            ? $discountedPrice
            : $this->getOriginalPrices();
    }

    /**
     * Get the current prices for the product (inc tax).
     */
    public function getCurrentPricesIncTax(): Collection
    {
        $discountedPrice = $this->getDiscountedPricesIncTax();

        return $discountedPrice->isNotEmpty()
            ? $discountedPrice
            : $this->getOriginalPricesIncTax();
    }

    /**
     * Get the discount amounts for the product (ex tax).
     */
    public function getDiscountAmounts(): Collection
    {
        $discountedPrice = $this->getDiscountedPrices();
        $discountAmount = collect();

        $this->getOriginalPrices()->each(function ($price) use ($discountedPrice, &$discountAmount) {
            $discountedPriceValue = $discountedPrice->firstWhere('currency.id', $price->currency->id);

            if (! $discountedPriceValue) {
                return;
            }

            $discountAmount->push(new Price(
                $price->value - $discountedPriceValue->value,
                $price->currency
            ));
        });

        return $discountAmount;
    }

    /**
     * Get the discount labels for the product.
     */
    public function getDiscountLabels(): Collection
    {
        $discount = $this->getDiscountManager()->getDiscountForPurchasable($this);
        $discountLabels = collect();

        if (! $discount) {
            return $discountLabels;
        }

        // Only show discount labels for amount off discounts
        if ($discount?->type !== AdvancedAmountOff::class) {
            return $discountLabels;
        }

        $this->getOriginalPrices()->each(function ($price) use ($discount, &$discountLabels) {
            $data = $discount->data ?? [];

            if (! $data['fixed_value'] && $data['percentage']) {
                $discountLabels->put($price->currency->code, $data['percentage'].'%');

                return;
            }

            $discountValue = $data['fixed_values'][$price->currency->code] ?? null;

            if (! $discountValue) {
                return;
            }

            $price = new Price(
                $discountValue,
                $price->currency
            );

            $discountLabels->put($price->currency->code, '-'.$price->formatted());
        });

        return $discountLabels;
    }

    /**
     * Get the original price for the product (inc tax).
     */
    public function getOriginalPriceIncTax(): ?Price
    {
        $price = $this->getOriginalPricesIncTax()
            ->firstWhere('currency.id', StorefrontSession::getCurrency()->id);

        if (! $price) {
            return null;
        }

        return new Price($price->value, $price->currency);
    }

    /**
     * Get prices formatted for data layer (original and sale).
     */
    public function getPricesForDatalayerAndGTM(): array
    {
        $prices = [];

        $defaultCurrency = Currency::getDefault();
        $originalPrice = $this->getOriginalPricesIncTax()->filter(fn ($price) => $price->currency->code === $defaultCurrency->code)->first();
        $price = $this->getCurrentPricesIncTax()->filter(fn ($price) => $price->currency->code === $defaultCurrency->code)->first();

        $prices['original'] = (float) number_format($originalPrice->value / 100, 2, '.', '');
        $prices['sale'] = (float) number_format($price->value / 100, 2, '.', '');
        $prices['discount'] = (float) number_format(($originalPrice->value - $price->value) / 100, 2, '.', '');
        $prices['currency'] = $price->currency->code;

        return $prices;
    }
}
