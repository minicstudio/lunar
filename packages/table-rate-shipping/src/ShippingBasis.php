<?php

namespace Lunar\Shipping;

use Illuminate\Support\Collection;
use Lunar\Models\CartLine;
use Lunar\Models\Contracts\Cart;
use Lunar\Models\Contracts\ProductVariant;

/**
 * Lines that count toward an automatic shipping price.
 *
 * A cart line is left out when `meta.preorder` is true or its variant is preorder.
 * A cart with `shipping_include_preorder` set keeps those lines. Checkout never sets
 * that key. The consultation quote uses it on a temporary cart.
 */
class ShippingBasis
{
    public const INCLUDE_PREORDER_META = 'shipping_include_preorder';

    /**
     * @return Collection<int, CartLine>
     */
    public static function lines(Cart $cart): Collection
    {
        $lines = $cart->lines;

        if (! self::includesPreorder($cart)) {
            $lines = $lines->reject(
                fn (object $line): bool => self::isPreorderLine($line)
            );
        }

        return $lines->values();
    }

    /**
     * Every product line is preorder, so none remain in the basis.
     */
    public static function isPreorderOnly(Cart $cart): bool
    {
        if (self::includesPreorder($cart)) {
            return false;
        }

        $productLines = $cart->lines->filter(
            fn (object $line): bool => self::isProductLine($line)
        );

        if ($productLines->isEmpty()) {
            return false;
        }

        return $productLines->every(
            fn (object $line): bool => self::isPreorderLine($line)
        );
    }

    /**
     * The consultation quote sets this on a temporary cart so preorder lines
     * are priced like simple products. Checkout carts do not set it.
     */
    public static function includesPreorder(Cart $cart): bool
    {
        $meta = $cart->meta ?? null;

        if ($meta instanceof \ArrayObject) {
            $meta = $meta->getArrayCopy();
        }

        return is_array($meta) && ($meta[self::INCLUDE_PREORDER_META] ?? null) === true;
    }

    public static function isPreorderLine(object $line): bool
    {
        $meta = $line->meta ?? null;

        if ((is_array($meta) || $meta instanceof \ArrayAccess) && (($meta['preorder'] ?? null) === true)) {
            return true;
        }

        $purchasable = $line->purchasable ?? null;

        return is_object($purchasable)
            && method_exists($purchasable, 'isPreorder')
            && $purchasable->isPreorder();
    }

    public static function isProductLine(object $line): bool
    {
        return ($line->purchasable ?? null) instanceof ProductVariant;
    }
}
