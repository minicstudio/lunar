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
    public static function calculateDiscountedPrice(Price $price, array $data, ?string $coupon = null): int
    {
        if ($data['fixed_value']) {
            $fixedValue = (int) ($data['fixed_values'][$price->currency->code] ?? 0);

            if (blank($coupon)) {
                $remaining = static::eligibleFixedValueRemaining($price->value, $fixedValue);

                return $remaining ?? $price->value;
            }

            return max(0, (int) ($price->value - $fixedValue));
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

        $cartLine->subTotalDiscounted = new Price(
            $subTotal - $amount,
            $cart->currency,
            1
        );

        $cartLine->subTotalDiscountedWithoutCoupon = new Price($subTotal - $amount, $cart->currency, 1);
        $cartLine->subTotalDiscountedWithoutCouponIncTax = $this->convertToIncTax($cartLine, $cartLine->subTotalDiscountedWithoutCoupon);

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
     * Apply a fixed value discount directly to a single cart line (used when
     * the discount has no coupon code). Deducts the configured amount per
     * unit (fixed value * quantity), capped by a configurable percentage of
     * the line's subtotal so it can never go negative.
     */
    public function applyFixedValueForLine(CartContract $cart, CartLineContract $cartLine): CartContract
    {
        if (! $this->checkDiscountConditions($cart)) {
            return $cart;
        }

        $amount = $this->estimateFixedValueAmountForLine($cartLine);

        if ($amount <= 0) {
            return $cart;
        }

        $unitPrice = $cartLine->unitPrice->value;
        $subTotal = $cartLine->subTotal->value;
        $subTotalDiscounted = $cartLine->subTotalDiscounted?->value ?: 0;
        $lineDiscount = $cartLine->discountTotal?->value ?: 0;

        if ($subTotalDiscounted) {
            $subTotal = $subTotalDiscounted;
        }

        $cartLine->unitPriceWithoutCoupon = new Price(
            (int) round($unitPrice - ($amount / $cartLine->quantity)),
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

        $cartLine->subTotalDiscounted = new Price(
            $subTotal - $amount,
            $cart->currency,
            1
        );

        $cartLine->subTotalDiscountedWithoutCoupon = new Price($subTotal - $amount, $cart->currency, 1);
        $cartLine->subTotalDiscountedWithoutCouponIncTax = $this->convertToIncTax($cartLine, $cartLine->subTotalDiscountedWithoutCoupon);

        $affectedLines = collect([
            new DiscountBreakdownLine(
                line: $cartLine,
                quantity: $cartLine->quantity,
                amount: new Price($amount, $cart->currency, 1),
            ),
        ]);

        if (! $cart->discounts) {
            $cart->discounts = collect();
        }

        $cart->discounts->push($this);

        $this->addDiscountBreakdown($cart, new DiscountBreakdown(
            price: new Price($amount, $cart->currency, 1),
            lines: $affectedLines,
            discount: $this->discount,
        ));

        return $cart;
    }

    /**
     * Estimate the fixed value discount amount for this line. Returns 0 if
     * not eligible (never partially applied/capped).
     */
    public function estimateFixedValueAmountForLine(CartLineContract $cartLine): int
    {
        $currency = $cartLine->unitPrice->currency;

        $fixedValuePerUnit = (int) ($this->discount->data['fixed_values'][$currency->code] ?? 0);

        if (! $fixedValuePerUnit) {
            return 0;
        }

        $subTotal = $cartLine->subTotalDiscounted?->value ?? $cartLine->subTotal->value;

        $rawAmount = $fixedValuePerUnit * $cartLine->quantity;

        $remaining = static::eligibleFixedValueRemaining($subTotal, $rawAmount);

        return $remaining === null ? 0 : $rawAmount;
    }

    /**
     * Returns the remaining amount if the deduction is eligible (within the
     * base amount and the minimum remaining %), otherwise null.
     */
    protected static function eligibleFixedValueRemaining(int $baseAmount, int $fixedValue): ?int
    {
        $remaining = $baseAmount - $fixedValue;

        if ($remaining < 0) {
            return null;
        }

        $minRemainingPercentage = config('lunar.discounts.fixed_value_minimum_remaining_percentage', 10);
        $minRemaining = (int) round($baseAmount * $minRemainingPercentage / 100);

        if ($remaining < $minRemaining) {
            return null;
        }

        return $remaining;
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

            $line->subTotalDiscounted = new Price(
                $subTotal - $amount,
                $cart->currency,
                1
            );

            $line->subTotalDiscountedWithoutCoupon = new Price($subTotal, $cart->currency, 1);
            $line->subTotalDiscountedWithoutCouponIncTax = $this->convertToIncTax($line, $line->subTotalDiscountedWithoutCoupon);

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
     * Apply a fixed value discount, splitting the configured amount across
     * eligible lines in proportion to their subtotal (any rounding remainder
     * is added back on top, see below). Mirrors Lunar\DiscountTypes\AmountOff,
     * but also maintains the *WithoutCoupon fields used for cart/order display.
     */
    public function applyFixedValueForCart(CartContract $cart): CartContract
    {
        $currency = $cart->currency;

        $value = (int) ($this->discount->data['fixed_values'][$currency->code] ?? 0);

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

            $line->subTotalDiscounted = new Price(
                $subTotal - $amount,
                $cart->currency,
                1
            );

            $line->subTotalDiscountedWithoutCoupon = new Price($subTotal, $cart->currency, 1);
            $line->subTotalDiscountedWithoutCouponIncTax = $this->convertToIncTax($line, $line->subTotalDiscountedWithoutCoupon);
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
