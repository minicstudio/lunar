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

        $cart->subTotalDiscountedWithoutCoupon = new Price(
            $cart->lines->sum(fn ($line) => $line->subTotalDiscountedWithoutCoupon?->value ?? 0),
            $cart->currency,
            1
        );

        $cart->subTotalDiscountedWithoutCouponIncTax = new Price($this->calculateTotalsIncTax($cart), $cart->currency, 1);

        $taxTotal = config('lunar.pricing.stored_inclusive_of_tax', false) ? 0 : $cart->taxTotal?->value;

        $cart->total = new Price(
            $cart->subTotalDiscountedWithoutCoupon?->value + $cart->shippingSubTotal?->value - $cart->couponTotal?->value + $taxTotal,
            $cart->currency
        );

        return $next($cart);
    }

    /**
     * Calculate cart-level inc tax totals by aggregating line-specific figures.
     */
    protected function calculateTotalsIncTax(Cart $cart): int
    {
        return $cart->lines->sum(function ($line) {
            return $line->subTotalDiscountedWithoutCouponIncTax?->value ?? 0;
        });
    }
}
