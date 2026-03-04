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
        $couponIncTax = 0;

        foreach ($cart->lines as $line) {
            $taxRate = $line?->purchasable?->getTaxRate() ?? 0.0;

            $subTotalWithoutCouponIncTax += $line->subTotalDiscountedWithoutCouponIncTax?->value ?? 0;

            $discountWithoutCouponIncTax += $line->discountTotalWithoutCouponIncTax?->value
                ?? $this->addTaxValue($line->discountTotalWithoutCoupon?->value ?? 0, $taxRate);

            $lineCouponValue = max(($line->discountTotal?->value ?? 0) - ($line->discountTotalWithoutCoupon?->value ?? 0), 0);

            if ($lineCouponValue > 0) {
                $couponIncTax += $this->addTaxValue($lineCouponValue, $taxRate);
            }
        }

        return [
            'subTotalWithoutCouponIncTax' => $subTotalWithoutCouponIncTax,
            'discountWithoutCouponIncTax' => $discountWithoutCouponIncTax,
            'couponIncTax' => $couponIncTax,
        ];
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
