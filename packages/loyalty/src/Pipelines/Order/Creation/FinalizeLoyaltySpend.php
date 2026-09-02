<?php

namespace Lunar\Loyalty\Pipelines\Order\Creation;

use Closure;
use Lunar\Loyalty\Services\LoyaltyEngine;
use Lunar\Loyalty\Services\LoyaltyRedeemer;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;

final class FinalizeLoyaltySpend
{
    public function __construct(
        protected LoyaltyRedeemer $redeemer,
        protected LoyaltyEngine $engine,
    ) {}

    /**
     * Spend loyalty points when an order is created from the cart.
     *
     * @param  Closure(OrderContract): mixed  $next
     */
    public function handle(OrderContract $order, Closure $next): mixed
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return $next($order);
        }

        /** @var Order $order */
        $cart = $order->cart;
        $points = $this->redeemer->getPointsFromCart($cart);

        if ($points <= 0) {
            return $next($order);
        }

        $this->redeemer->validateRedemption($cart, $points);
        $this->engine->spendForOrder($order, $points);

        return $next($order);
    }
}
