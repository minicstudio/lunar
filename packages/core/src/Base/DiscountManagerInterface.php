<?php

namespace Lunar\Base;

use Illuminate\Support\Collection;
use Lunar\Base\DataTransferObjects\CartDiscount;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

interface DiscountManagerInterface
{
    /**
     * Add a discount type by classname
     *
     * @param  string  $classname
     */
    public function addType($classname): self;

    /**
     * Return the available discount types.
     */
    public function getTypes(): Collection;

    /**
     * Add an applied discount
     */
    public function addApplied(CartDiscount $cartDiscount): self;

    /**
     * Return the applied discounts
     */
    public function getApplied(): Collection;

    /**
     * Apply discounts for a given cart.
     */
    public function apply(Cart $cart): Cart;

    /**
     * Validate a given coupon against all system discounts.
     */
    public function validateCoupon(string $coupon): bool;

    /**
     * Get the best discount for the purchasable only.
     */
    public function getDiscountForPurchasable(null|Product|ProductVariant $purchasable = null): ?Discount;
}
