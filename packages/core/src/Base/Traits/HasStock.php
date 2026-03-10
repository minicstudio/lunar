<?php

namespace Lunar\Base\Traits;

use Lunar\Base\Purchasable;
use Lunar\Exceptions\InsufficientStockException;
use Lunar\Exceptions\OutOfStockException;

trait HasStock
{
    /**
     * Check if a purchasable item has sufficient stock for the given quantity.
     */
    public function checkPurchasableStockOrFail(Purchasable $purchasable, int $quantity): bool
    {
        // If stock checking is disabled, skip the checks
        if (! config('lunar.cart.stock_check.enabled')) {
            return true;
        }

        // Check if the purchasable is in stock
        if (! $purchasable->canBeFulfilledAtQuantity(1)) {
            throw new OutOfStockException;
        }

        // Check if the quantity is valid
        if (! $purchasable->canBeFulfilledAtQuantity($quantity)) {
            throw new InsufficientStockException;
        }

        return true;
    }
}
