<?php

namespace Lunar\Pipelines\Cart;

use Closure;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdownItem;
use Lunar\DataTypes\Price;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;

final class ApplyDiscountFreeShipping
{
    /**
     * Waive the selected shipping when the cart has a free-shipping discount.
     *
     * Reads the discount recorded by ApplyDiscounts and the breakdown built by
     * ApplyShipping in the same calculation, so it must run after both. Hosts
     * order those two stages either way, so it is listed directly before
     * CalculateTax, which is after both in every order. An explicit shipping
     * override, such as an order split target, keeps its price.
     *
     * @param  Closure(CartContract): mixed  $next
     */
    public function handle(CartContract $cart, Closure $next): mixed
    {
        /** @var Cart $cart */
        $cart->shippingDiscountTotal = null;

        if (! $cart->freeShippingDiscount || $cart->shippingOptionOverride) {
            return $next($cart);
        }

        if (! $cart->shippingBreakdown || $cart->shippingBreakdown->items->isEmpty()) {
            return $next($cart);
        }

        $waived = $cart->shippingBreakdown->items->sum('price.value');

        if ($waived > 0) {
            $cart->shippingDiscountTotal = new Price($waived, $cart->currency, 1);
        }

        $cart->shippingBreakdown->items = $cart->shippingBreakdown->items->map(
            fn (ShippingBreakdownItem $item) => new ShippingBreakdownItem(
                name: $item->name,
                identifier: $item->identifier,
                price: new Price(0, $cart->currency, 1),
            )
        );

        $cart->shippingSubTotal = new Price(0, $cart->currency, 1);

        if ($cart->shippingAddress) {
            $cart->shippingAddress->shippingSubTotal = new Price(0, $cart->currency, 1);
            $cart->shippingAddress->shippingTotal = new Price(0, $cart->currency, 1);
        }

        return $next($cart);
    }
}
