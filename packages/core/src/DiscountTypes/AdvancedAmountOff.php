<?php

namespace Lunar\DiscountTypes;

use Lunar\Base\ValueObjects\Cart\DiscountBreakdown;
use Lunar\Base\ValueObjects\Cart\DiscountBreakdownLine;
use Lunar\DataTypes\Price;
use Lunar\Models\Collection;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\Contracts\CartLine as CartLineContract;

class AdvancedAmountOff extends AbstractDiscountType
{
    /**
     * Return the name of the discount.
     */
    public function getName(): string
    {
        return __('lunarpanel::discount.form.advanced_amount_off.heading');
    }

    /**
     * Called just before cart totals are calculated.
     */
    public function apply(CartContract $cart): CartContract
    {
        if (! $this->checkDiscountConditions($cart)) {
            return $cart;
        }

        if ($this->discount->data['fixed_value'] ?? false) {
            return $this->applyFixedValueForCart($cart);
        }

        return $this->applyCouponForCart($cart);
    }

    /**
     * Static method to calculate discounted price.
     * This can be used dynamically by other classes.
     */
    public static function calculateDiscountedPrice(Price $price, array $data): int
    {
        if ($data['fixed_value']) {
            return (int) ($price->value - $data['fixed_values'][$price->currency->code]);
        }

        return (int) ($price->value - round($price->value * $data['percentage'] / 100));
    }

    /**
     * Apply the percentage to the cart line. Code was taken from the lunar core package.
     */
    public function applyPercentageForLine(CartContract $cart, CartLineContract $cartLine): CartContract
    {
        $data = $this->discount->data;

        if (! $this->checkDiscountConditions($cart)) {
            return $cart;
        }

        $value = $data['percentage'] ?? 0;

        $affectedLines = collect();
        $totalDiscount = 0;

        $unitPrice = $cartLine->unitPrice->value;
        $subTotal = $cartLine->subTotal->value;
        $subTotalDiscounted = $cartLine->subTotalDiscounted?->value ?: 0;
        $lineDiscount = $cartLine->discountTotal?->value ?: 0;

        if ($subTotalDiscounted) {
            $subTotal = $subTotalDiscounted;
        }

        $amount = (int) round($subTotal * ($value / 100));

        if ($amount <= 0) {
            return $cart;
        }

        $totalDiscount += $amount;

        $cartLine->unitPriceWithoutCoupon = new Price(
            (int) round($unitPrice - $unitPrice * $value / 100),
            $cart->currency,
            1
        );
        $cartLine->unitPriceWithoutCouponIncTax = $this->convertToIncTax($cartLine, $cartLine->unitPriceWithoutCoupon);

        $cartLine->discountTotal = new Price(
            $lineDiscount + $amount,
            $cart->currency,
            1
        );

        $cartLine->discountTotalWithoutCoupon = new Price(
            $lineDiscount + $amount,
            $cart->currency,
            1
        );
        $cartLine->discountTotalWithoutCouponIncTax = $this->convertToIncTax($cartLine, $cartLine->discountTotalWithoutCoupon);

        $cartLine->subTotalDiscounted = new Price(
            $subTotal - $amount,
            $cart->currency,
            1
        );

        $cartLine->subTotalDiscountedWithoutCouponIncTax = $this->convertToIncTax($cartLine, new Price($subTotal - $amount, $cart->currency, 1));

        $affectedLines->push(new DiscountBreakdownLine(
            line: $cartLine,
            quantity: $cartLine->quantity,
            amount: new Price($amount, $cart->currency, 1),
        ));

        if (! $cart->discounts) {
            $cart->discounts = collect();
        }

        if ($totalDiscount <= 0) {
            return $cart;
        }

        $cart->discounts->push($this);

        $this->addDiscountBreakdown($cart, new DiscountBreakdown(
            price: new Price($totalDiscount, $cart->currency, 1),
            lines: $affectedLines,
            discount: $this->discount,
        ));

        return $cart;
    }

    /**
     * Apply the percentage to the cart line. Code was taken from the lunar core package.
     */
    public function applyCouponForCart(CartContract $cart): CartContract
    {
        $data = $this->discount->data;

        if (! $this->checkDiscountConditions($cart)) {
            return $cart;
        }

        // DiscountManager::apply() calls this method directly for coupons, so the
        // fixed-value dispatch must live here (not only in apply()).
        if ($data['fixed_value'] ?? false) {
            return $this->applyFixedValueForCart($cart);
        }

        $lines = $this->getEligibleLines($cart);

        $affectedLines = collect();
        $totalDiscount = 0;

        foreach ($lines as $line) {
            $subTotal = $line->subTotal->value;
            $subTotalDiscounted = $line->subTotalDiscounted?->value ?: 0;
            $lineDiscount = $line->discountTotal?->value ?: 0;

            if ($subTotalDiscounted) {
                $subTotal = $subTotalDiscounted;
            }

            $amount = (int) ($subTotal * ($data['percentage'] / 100));

            $totalDiscount += $amount;

            $line->discountTotal = new Price(
                $lineDiscount + $amount,
                $cart->currency,
                1
            );

            $line->discountTotalWithoutCoupon = new Price(
                $lineDiscount,
                $cart->currency,
                1
            );
            $line->discountTotalWithoutCouponIncTax = $this->convertToIncTax($line, $line->discountTotalWithoutCoupon);

            $line->subTotalDiscounted = new Price(
                $subTotal - $amount,
                $cart->currency,
                1
            );

            $line->subTotalDiscountedWithoutCouponIncTax = $this->convertToIncTax($line, new Price($subTotal, $cart->currency, 1));

            $affectedLines->push(new DiscountBreakdownLine(
                line: $line,
                quantity: $line->quantity,
                amount: new Price($amount, $cart->currency, 1),
            ));
        }

        if (! $cart->discounts) {
            $cart->discounts = collect();
        }

        if ($totalDiscount <= 0) {
            return $cart;
        }

        $cart->discounts->push($this);

        $this->addDiscountBreakdown($cart, new DiscountBreakdown(
            price: new Price($totalDiscount, $cart->currency, 1),
            lines: $affectedLines,
            discount: $this->discount,
        ));

        return $cart;
    }

    /**
     * Apply a fixed value discount to the cart.
     *
     * The configured amount is expressed in the stored pricing basis (net when
     * config('lunar.pricing.stored_inclusive_of_tax') is false, gross when true), so it is
     * distributed across the eligible lines proportionally to each line's stored subtotal
     * with no tax conversion. Any rounding remainder is spread over the lines that still have
     * a balance so the per-line amounts sum to exactly the configured value, and the breakdown
     * total keeps that value so the order reports exactly the configured fixed value. The
     * *IncTax display-mirror fields are still derived once via convertToIncTax for the
     * frontend (a no-op when prices are already stored gross). Mirrors
     * Lunar\DiscountTypes\AmountOff, but also maintains the *WithoutCoupon* tracking fields
     * used by the frontend display.
     */
    public function applyFixedValueForCart(CartContract $cart): CartContract
    {
        $currency = $cart->currency;

        $decimal = ($this->discount->data['fixed_values'][$currency->code] ?? 0) / $currency->factor;
        // The configured fixed value is expressed in the stored pricing basis.
        $value = (int) bcmul($decimal, $currency->factor);

        $lines = $this->getEligibleLines($cart);

        $linesSubtotal = $lines->sum(function ($line) {
            return ($line->subTotalDiscounted ?? $line->subTotal)->value;
        });

        if (! $value || $linesSubtotal < $value) {
            return $cart;
        }

        $divisionalAmount = $value / $linesSubtotal;
        $remaining = $value;

        $affectedLines = collect();

        foreach ($lines as $line) {
            $source = $line->subTotalDiscounted ?? $line->subTotal;
            $subTotal = $source->value;
            $lineDiscount = $line->discountTotal?->value ?: 0;

            $amount = (int) floor($subTotal * $divisionalAmount);

            if ($amount > $subTotal) {
                $amount = $subTotal;
            }

            $remaining -= $amount;

            $line->discountTotal = new Price(
                $lineDiscount + $amount,
                $cart->currency,
                1
            );

            $line->discountTotalWithoutCoupon = new Price(
                $lineDiscount,
                $cart->currency,
                1
            );
            $line->discountTotalWithoutCouponIncTax = $this->convertToIncTax($line, $line->discountTotalWithoutCoupon);

            $line->subTotalDiscounted = new Price(
                $subTotal - $amount,
                $cart->currency,
                1
            );

            $line->subTotalDiscountedWithoutCouponIncTax = $this->convertToIncTax($line, new Price($subTotal, $cart->currency, 1));
        }

        // Spread any rounding remainder over the lines that still have a balance.
        if ($remaining > 0) {
            $lines->filter(function ($line) {
                return $line->subTotalDiscounted->value > 0;
            })->each(function ($line) use ($cart, &$remaining) {
                if ($remaining <= 0) {
                    return;
                }

                $take = min($line->subTotalDiscounted->value, $remaining);
                $remaining -= $take;

                $line->discountTotal = new Price(
                    $line->discountTotal->value + $take,
                    $cart->currency,
                    1
                );

                $line->subTotalDiscounted = new Price(
                    $line->subTotalDiscounted->value - $take,
                    $cart->currency,
                    1
                );
            });
        }

        // Record the coupon amount removed from each line (discount total minus the
        // pre-coupon/automatic portion) so order-level accessors can read it back.
        foreach ($lines as $line) {
            $lineCoupon = ($line->discountTotal?->value ?? 0) - ($line->discountTotalWithoutCoupon?->value ?? 0);

            if ($lineCoupon <= 0) {
                continue;
            }

            $affectedLines->push(new DiscountBreakdownLine(
                line: $line,
                quantity: $line->quantity,
                amount: new Price($lineCoupon, $cart->currency, 1),
            ));
        }

        if (! $cart->discounts) {
            $cart->discounts = collect();
        }

        $totalDiscount = $value - $remaining;

        if ($totalDiscount <= 0) {
            return $cart;
        }

        $cart->discounts->push($this);

        $this->addDiscountBreakdown($cart, new DiscountBreakdown(
            price: new Price($totalDiscount, $cart->currency, 1),
            lines: $affectedLines,
            discount: $this->discount,
        ));

        return $cart;
    }

    /**
     * Return the eligible lines for the discount.
     */
    protected function getEligibleLines(CartContract $cart): \Illuminate\Support\Collection
    {
        $collectionIds = $this->discount->collections->where('pivot.type', 'limitation')->pluck('id');
        $collectionExclusionIds = $this->discount->collections->where('pivot.type', 'exclusion')->pluck('id');

        $brandIds = $this->discount->brands->where('pivot.type', 'limitation')->pluck('id');
        $brandExclusionIds = $this->discount->brands->where('pivot.type', 'exclusion')->pluck('id');

        $productIds = $this->discount->discountableLimitations
            ->reject(fn ($limitation) => ! $limitation->discountable)
            ->map(fn ($limitation) => get_class($limitation->discountable).'::'.$limitation->discountable->id);

        $productExclusionIds = $this->discount->discountableExclusions
            ->reject(fn ($limitation) => ! $limitation->discountable)
            ->map(fn ($limitation) => get_class($limitation->discountable).'::'.$limitation->discountable->id);

        $lines = $cart->lines;

        if ($collectionIds->count()) {
            $lines = $lines->filter(function ($line) use ($collectionIds) {
                return $line->purchasable->product()->whereHas('collections', function ($query) use ($collectionIds) {
                    $query->whereIn((new Collection)->getTable().'.id', $collectionIds);
                })->exists();
            });
        }

        if ($collectionExclusionIds->count()) {
            $lines = $lines->reject(function ($line) use ($collectionExclusionIds) {
                return $line->purchasable->product()->whereHas('collections', function ($query) use ($collectionExclusionIds) {
                    $query->whereIn((new Collection)->getTable().'.id', $collectionExclusionIds);
                })->exists();
            });
        }

        if ($brandIds->count()) {
            $lines = $lines->reject(function ($line) use ($brandIds) {
                return ! $brandIds->contains($line->purchasable->product->brand_id);
            });
        }

        if ($brandExclusionIds->count()) {
            $lines = $lines->reject(function ($line) use ($brandExclusionIds) {
                return $brandExclusionIds->contains($line->purchasable->product->brand_id);
            });
        }

        if ($productIds->count()) {
            $lines = $lines->filter(function ($line) use ($productIds) {
                return $productIds->contains(get_class($line->purchasable).'::'.$line->purchasable->id) || $productIds->contains(get_class($line->purchasable->product).'::'.$line->purchasable->product->id);
            });
        }

        if ($productExclusionIds->count()) {
            $lines = $lines->reject(function ($line) use ($productExclusionIds) {
                return $productExclusionIds->contains(get_class($line->purchasable).'::'.$line->purchasable->id) || $productExclusionIds->contains(get_class($line->purchasable->product).'::'.$line->purchasable->product->id);
            });
        }

        return $lines;
    }

    /**
     * Convert a price to include tax.
     */
    protected function convertToIncTax(CartLineContract $line, Price $price): Price
    {
        if (config('lunar.pricing.stored_inclusive_of_tax', false)) {
            return $price;
        }

        $taxRate = $line->purchasable?->getTaxRate() ?? 0.0;

        return new Price(
            (int) round($price->value * (1 + $taxRate)),
            $price->currency,
            $price->unitQty
        );
    }
}
