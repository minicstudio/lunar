<?php

namespace Lunar\Validation\CartLine;

use Lunar\Base\Purchasable;
use Lunar\Base\Traits\HasStock;
use Lunar\Facades\CartSession;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Validation\BaseValidator;

class CartLineStock extends BaseValidator
{
    use HasStock;

    /**
     * {@inheritDoc}
     */
    public function validate(): bool
    {
        $quantity = $this->parameters['quantity'] ?? 0;
        $cartLineId = $this->parameters['cartLineId'] ?? null;
        $cart = $this->parameters['cart'] ?? null;
        // In update scenarios, the purchasable is not present, so we retrieve it from the cart
        $purchasable = $this->parameters['purchasable'] ?? $this->getPurchasableFromCart($cart, $cartLineId);

        $currentLine = $this->getCurrentCartLine($purchasable);

        // Handle case when adding a new item that's already in the cart (e.g. multiple "Add to cart" clicks)
        if (! $cartLineId && $currentLine) {
            $quantity += $currentLine->quantity;
        }

        // No need to check stock if quantity is decreasing
        if ($currentLine && $currentLine->quantity > $quantity) {
            return true;
        }

        return $this->checkPurchasableStockOrFail($purchasable, $quantity);
    }

    /**
     * Get the purchasable item from the cart by cart line ID.
     */
    protected function getPurchasableFromCart(Cart $cart, int $cartLineId): ?Purchasable
    {
        return $cart?->lines
            ?->first(fn ($cartLine) => $cartLine->id === $cartLineId)
            ?->purchasable;
    }

    /**
     * Get the current cart line for the given purchasable.
     */
    protected function getCurrentCartLine(Purchasable $purchasable): ?CartLine
    {
        return CartSession::current()?->lines
            ?->first(fn ($line) => $line->purchasable->is($purchasable));
    }
}
