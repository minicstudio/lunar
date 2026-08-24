<?php

namespace Lunar\Validation\CartLine;

use Lunar\Actions\Carts\GetExistingCartLine;
use Lunar\Base\Purchasable;
use Lunar\Exceptions\Carts\MinimumQuantityException;
use Lunar\Facades\CartSession;
use Lunar\Models\CartLine;
use Lunar\Validation\BaseValidator;

class CartLineQuantity extends BaseValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate(): bool
    {
        $quantity = $this->parameters['quantity'] ?? 0;
        $purchasable = $this->parameters['purchasable'] ?? null;
        $cartLineId = $this->parameters['cartLineId'] ?? null;
        $cart = $this->parameters['cart'] ?? null;

        if ($cartLineId && ! $purchasable && $cart) {
            $purchasable = $cart->lines->first(
                fn ($cartLine) => $cartLine->id == $cartLineId
            )?->purchasable;
        }

        if ($quantity < 1) {
            $this->fail(
                'cart',
                __('lunar::exceptions.invalid_cart_line_quantity', [
                    'quantity' => $quantity,
                ])
            );
        }

        if ($quantity > 1000000) {
            $this->fail(
                'cart',
                __('lunar::exceptions.maximum_cart_line_quantity', [
                    'quantity' => 1000000,
                ])
            );
        }

        if ($purchasable) {
            $minCheckQuantity = $quantity;

            if (! $cartLineId && $currentLine = $this->getCurrentCartLine($purchasable)) {
                $minCheckQuantity += $currentLine->quantity;
            }

            if ($minCheckQuantity < $purchasable->min_quantity) {
                $this->fail(
                    'cart',
                    __('lunar::exceptions.minimum_quantity', [
                        'quantity' => $purchasable->min_quantity,
                    ]),
                    MinimumQuantityException::class
                );
            }
        }

        if ($purchasable && ($quantity % ($purchasable->quantity_increment ?? 1)) !== 0) {
            $this->fail(
                'cart',
                __('lunar::exceptions.quantity_increment', [
                    'quantity' => $quantity,
                    'increment' => $purchasable->quantity_increment,
                ])
            );
        }

        return $this->pass();
    }

    /**
     * Get the current cart line for the given purchasable.
     */
    protected function getCurrentCartLine(Purchasable $purchasable): ?CartLine
    {
        $cart = $this->parameters['cart'] ?? CartSession::current();

        if (! $cart) {
            return null;
        }

        // If new keys are ever stored in meta, check whether they should affect this cart line match.
        $currentLine = app(
            config('lunar.cart.actions.get_existing_cart_line', GetExistingCartLine::class)
        )->execute(
            cart: $cart,
            purchasable: $purchasable,
            meta: (array) ($this->parameters['meta'] ?? [])
        );

        return $currentLine instanceof CartLine ? $currentLine : null;
    }
}
