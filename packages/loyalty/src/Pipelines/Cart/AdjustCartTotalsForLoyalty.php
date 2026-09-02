<?php

namespace Lunar\Loyalty\Pipelines\Cart;

use Closure;
use Lunar\DataTypes\Price;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Pipelines\Cart\Calculate;

final class AdjustCartTotalsForLoyalty
{
    public function __construct(
        protected Calculate $calculate,
    ) {}

    /**
     * Reconcile cart totals after core calculation when loyalty is applied.
     *
     * @param  Closure(CartContract): mixed  $next
     */
    public function handle(CartContract $cart, Closure $next): mixed
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return $next($cart);
        }

        /** @var Cart $cart */
        $loyaltyExTax = (int) (($cart->meta?->toArray() ?? [])['loyalty_discount_minor'] ?? 0);

        if ($loyaltyExTax <= 0) {
            $cart->loyaltyTotalIncTax = null;

            return $next($cart);
        }

        $loyaltyIncTax = $this->calculate->convertExTaxAmountToIncTaxUsingLines(
            $cart,
            $loyaltyExTax,
            useDiscountedSubtotal: true,
        );

        $cart->loyaltyTotalIncTax = new Price($loyaltyIncTax, $cart->currency, 1);
        $cart->total = new Price(
            max(0, $this->checkoutTotalMinor($cart, $loyaltyIncTax)),
            $cart->currency,
            1
        );

        return $next($cart);
    }

    /**
     * Build the checkout total from the same components shown in the order summary.
     */
    protected function checkoutTotalMinor(Cart $cart, int $loyaltyIncTax): int
    {
        return ($cart->subTotalDiscountedWithoutCouponIncTax?->value ?? 0)
            + ($cart->shippingTotal?->value ?? 0)
            - ($cart->couponTotalIncTax?->value ?? 0)
            - $loyaltyIncTax;
    }
}
