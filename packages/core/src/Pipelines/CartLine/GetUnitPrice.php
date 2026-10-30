<?php

namespace Lunar\Pipelines\CartLine;

use Closure;
use Lunar\DataTypes\Price;
use Lunar\Exceptions\Carts\CartNotFoundException;
use Lunar\Exceptions\Carts\PurchasableNotFoundException;
use Lunar\Facades\Pricing;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Contracts\CartLine as CartLineContract;
use Spatie\LaravelBlink\BlinkFacade as Blink;

class GetUnitPrice
{
    /**
     * Called just before cart totals are calculated.
     *
     * @param  Closure(CartLineContract): mixed  $next
     * @return Closure
     */
    public function handle(CartLineContract $cartLine, Closure $next)
    {
        /** @var CartLine $cart */
        $purchasable = $cartLine->purchasable;
        $cart = $cartLine->cart;

        // handle if cart is null
        if (! $cart) {
            throw new CartNotFoundException('Cart not found for cart line ID: '.$cartLine->id);
        }

        if ($customer = $cart->customer) {
            $customerGroups = $customer->customerGroups;
        } else {
            $customerGroups = $cart->user?->customers->pluck('customerGroups')->flatten();
        }

        $currency = Blink::once('currency_'.$cart->currency_id, function () use ($cart) {
            return $cart->currency;
        });

        if (! $purchasable) {
            throw new PurchasableNotFoundException('Purchasable not found for cart line.');
        }

        $override = $cart instanceof Cart ? ($cart->unitPriceOverrides[$cartLine->id] ?? null) : null;

        if (is_array($override) && array_key_exists('value', $override)) {
            $unitQuantity = max(1, (int) ($override['unit_quantity'] ?? $purchasable->getUnitQuantity()));
            $value = (int) $override['value'];
            $inclusive = prices_inc_tax()
                ? $value
                : (int) round($value * (1 + $purchasable->getTaxRate()));

            $cartLine->unitPrice = new Price($value, $cart->currency, $unitQuantity);
            $cartLine->unitPriceInclTax = new Price($inclusive, $cart->currency, $unitQuantity);

            return $next($cartLine);
        }

        $priceResponse = Pricing::currency($currency)
            ->qty($cartLine->quantity)
            ->currency($cart->currency)
            ->customerGroups($customerGroups)
            ->for($purchasable)
            ->get();

        $cartLine->unitPrice = new Price(
            $priceResponse->matched->price->value,
            $cart->currency,
            $purchasable->getUnitQuantity()
        );

        $cartLine->unitPriceInclTax = new Price(
            $priceResponse->matched->priceIncTax($cart->taxZone)->value,
            $cart->currency,
            $purchasable->getUnitQuantity()
        );

        return $next($cartLine);
    }
}
