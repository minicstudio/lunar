<?php

namespace Lunar\Pipelines\Cart;

use Closure;
use Lunar\DataTypes\Price;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;

class Calculate
{
    /**
     * Called just before cart totals are calculated.
     *
     * @param  Closure(CartContract): mixed  $next
     */
    public function handle(CartContract $cart, Closure $next): mixed
    {
        /** @var Cart $cart */
        $discountTotal = $cart->lines->sum('discountTotal.value');

        $subTotal = $cart->lines->sum('subTotal.value');

        $subTotalDiscounted = $cart->lines->sum(function ($line) {
            return $line->subTotalDiscounted ?
                $line->subTotalDiscounted->value :
                $line->subTotal->value;
        });

        $cart->subTotal = new Price($subTotal, $cart->currency, 1);
        $cart->subTotalDiscounted = new Price($subTotalDiscounted, $cart->currency, 1);
        $cart->discountTotal = new Price($discountTotal, $cart->currency, 1);

        /** @var Cart $cart */
        $couponDiscounts = $cart->discountBreakdown->filter(function ($breakdown) {
            return $breakdown->discount->coupon !== null;
        });

        $discountTotalWithoutCoupon = $cart->discountBreakdown->filter(function ($breakdown) {
            return $breakdown->discount->coupon === null;
        })->sum('price.value');

        $couponDiscountTotal = $couponDiscounts->sum('price.value');

        $cart->loadMissing('lines.purchasable');

        // Add extra attributes to the cart to be used in the frontend
        $cart->couponTotal = new Price($couponDiscountTotal, $cart->currency, 1);
        $cart->discountTotalWithoutCoupon = new Price($discountTotalWithoutCoupon, $cart->currency, 1);

        $cartTotalsIncTax = $this->calculateTotalsIncTax($cart);

        $cart->subTotalDiscountedWithoutCouponIncTax = new Price($cartTotalsIncTax['subTotalWithoutCouponIncTax'], $cart->currency, 1);
        $cart->couponTotalIncTax = new Price($cartTotalsIncTax['couponIncTax'], $cart->currency, 1);
        $cart->discountTotalWithoutCouponIncTax = new Price($cartTotalsIncTax['discountWithoutCouponIncTax'], $cart->currency, 1);

        $cart->total = new Price($cart->subTotalDiscountedWithoutCouponIncTax?->value + $cart->shippingTotal?->value - $cart->couponTotalIncTax?->value, $cart->currency);

        return $next($cart);
    }

    /**
     * Calculate cart-level inc tax totals by aggregating line-specific figures.
     */
    protected function calculateTotalsIncTax(Cart $cart): array
    {
        $subTotalWithoutCouponIncTax = 0;
        $discountWithoutCouponIncTax = 0;

        foreach ($cart->lines as $line) {
            $taxRate = $line?->purchasable?->getTaxRate() ?? 0.0;

            $subTotalWithoutCouponIncTax += $line->subTotalDiscountedWithoutCouponIncTax?->value ?? 0;

            $discountWithoutCouponIncTax += $line->discountTotalWithoutCouponIncTax?->value
                ?? $this->addTaxValue($line->discountTotalWithoutCoupon?->value ?? 0, $taxRate);
        }

        $couponIncTax = $this->calculateCouponIncTax($cart);

        return [
            'subTotalWithoutCouponIncTax' => $subTotalWithoutCouponIncTax,
            'discountWithoutCouponIncTax' => $discountWithoutCouponIncTax,
            'couponIncTax' => $couponIncTax,
        ];
    }

    /**
     * Calculate coupon discount including tax from the authoritative breakdown.
     *
     * Line-level deltas between discountTotal and discountTotalWithoutCoupon can
     * include post-coupon reductions such as loyalty points, so breakdown is used.
     */
    protected function calculateCouponIncTax(Cart $cart): int
    {
        $couponExTax = $cart->discountBreakdown
            ->filter(fn ($breakdown) => filled($breakdown->discount->coupon ?? null))
            ->sum(fn ($breakdown) => $breakdown->price->value);

        if ($couponExTax <= 0) {
            return 0;
        }

        return $this->convertExTaxAmountToIncTaxUsingLines($cart, $couponExTax);
    }

    /**
     * Convert an ex-tax cart discount to inc-tax using line subtotal weighting.
     */
    public function convertExTaxAmountToIncTaxUsingLines(
        Cart $cart,
        int $exTaxMinor,
        bool $useDiscountedSubtotal = false,
    ): int {
        if ($exTaxMinor <= 0) {
            return 0;
        }

        if (config('lunar.pricing.stored_inclusive_of_tax', false)) {
            return $exTaxMinor;
        }

        $eligibleSubtotal = $cart->lines->sum(function ($line) use ($useDiscountedSubtotal) {
            if ($useDiscountedSubtotal) {
                return $line->subTotalDiscounted?->value ?? $line->subTotal?->value ?? 0;
            }

            return $line->subTotal?->value ?? 0;
        });

        if ($eligibleSubtotal <= 0) {
            return $exTaxMinor;
        }

        $incTaxMinor = 0;

        foreach ($cart->lines as $line) {
            $lineSubtotal = $useDiscountedSubtotal
                ? ($line->subTotalDiscounted?->value ?? $line->subTotal?->value ?? 0)
                : ($line->subTotal?->value ?? 0);

            if ($lineSubtotal <= 0) {
                continue;
            }

            $taxRate = $line->purchasable?->getTaxRate() ?? 0.0;
            $share = $lineSubtotal / $eligibleSubtotal;
            $incTaxMinor += (int) round($exTaxMinor * $share * (1 + $taxRate));
        }

        return $incTaxMinor > 0 ? $incTaxMinor : $exTaxMinor;
    }

    /**
     * Add tax to a given value based on the provided tax rate.
     */
    protected function addTaxValue(int $value, float $taxRate): int
    {
        if (config('lunar.pricing.stored_inclusive_of_tax', false)) {
            return $value;
        }

        return (int) ($value * (1 + $taxRate));
    }
}
