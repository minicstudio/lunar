<?php

namespace Lunar\Shipping;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Schema;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\CartLine;
use Lunar\Models\Order;
use Lunar\Pipelines\Cart\CalculateLines;
use Lunar\Shipping\Facades\Shipping;
use Lunar\Shipping\Models\ShippingMethod;
use Throwable;

/**
 * Price the order's shipping method as if every product line were a simple product.
 *
 * Checkout leaves preorder lines out of the automatic price. The consultation
 * modal starts from this quote instead, on a temporary cart that is deleted
 * before the method returns.
 */
class PreorderConsultationShipping
{
    /**
     * Minor-unit price, or null when the method cannot be priced.
     *
     * @param  list<int>|null  $onlyLineIds  When set, only these order lines are quoted.
     */
    public static function suggestedMinor(Order $order, ?array $onlyLineIds = null): ?int
    {
        if (! Schema::hasTable((new ShippingMethod)->getTable())) {
            return null;
        }

        $identifier = $order->shippingLines()->value('identifier');

        if (! filled($identifier)) {
            return null;
        }

        $currency = $order->currency;
        $address = $order->shippingAddress;

        if (! $currency || ! $address?->country_id || blank($address->postcode)) {
            return null;
        }

        $cart = null;

        try {
            $cartModel = Cart::modelClass();

            $cart = $cartModel::withoutEvents(fn (): Cart => $cartModel::query()->create([
                'currency_id' => $currency->id,
                'channel_id' => $order->channel_id,
                'customer_id' => $order->customer_id,
                'user_id' => $order->user_id,
                'meta' => [ShippingBasis::INCLUDE_PREORDER_META => true],
            ]));

            CartAddress::withoutEvents(function () use ($cart, $address): void {
                $cart->addresses()->create([
                    'country_id' => $address->country_id,
                    'postcode' => $address->postcode,
                    'state' => null,
                    'type' => 'shipping',
                    'first_name' => $address->first_name,
                    'last_name' => $address->last_name,
                    'line_one' => $address->line_one ?: '—',
                    'city' => $address->city ?: '—',
                ]);
            });

            $onlyLineIds = $onlyLineIds === null
                ? null
                : array_fill_keys(array_map('intval', $onlyLineIds), true);

            CartLine::withoutEvents(function () use ($cart, $order, $onlyLineIds): void {
                foreach ($order->productLines as $line) {
                    if (! $line->purchasable_type || ! $line->purchasable_id) {
                        continue;
                    }

                    if ($onlyLineIds !== null && ! isset($onlyLineIds[(int) $line->id])) {
                        continue;
                    }

                    $meta = $line->meta instanceof \ArrayObject
                        ? $line->meta->getArrayCopy()
                        : (is_array($line->meta) ? $line->meta : []);

                    $cart->lines()->create([
                        'purchasable_type' => $line->purchasable_type,
                        'purchasable_id' => $line->purchasable_id,
                        'quantity' => $line->quantity,
                        'meta' => $meta,
                    ]);
                }
            });

            $cart->load('lines.purchasable', 'shippingAddress.country', 'currency');

            try {
                app(Pipeline::class)
                    ->send($cart)
                    ->through([CalculateLines::class])
                    ->thenReturn();
            } catch (Throwable) {
                // A weight price does not need line totals.
            }

            $rate = Shipping::shippingRates($cart)->get()->first(
                fn (object $rate): bool => $rate->getIdentifier() === $identifier
            );

            $option = $rate?->getShippingOption($cart);

            if (! $option) {
                return null;
            }

            return (int) $option->price->value;
        } catch (Throwable) {
            return null;
        } finally {
            if ($cart?->exists) {
                CartLine::withoutEvents(fn () => $cart->lines()->delete());
                CartAddress::withoutEvents(fn () => $cart->addresses()->delete());
                Cart::withoutEvents(fn () => $cart->delete());
            }
        }
    }
}
