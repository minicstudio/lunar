<?php

namespace Lunar\ProductImport\Enums;

/**
 * What a staged row is: the product of its group, one of its variants, or
 * both (a product with one variant, or one variant row of a grouped product).
 */
enum RowRole: string
{
    case Product = 'product';
    case Variant = 'variant';
    case ProductAndVariant = 'product_and_variant';

    /**
     * Whether the row's fields serve the product.
     */
    public function isProduct(): bool
    {
        return $this !== self::Variant;
    }

    /**
     * Whether the row creates or updates a variant.
     */
    public function isVariant(): bool
    {
        return $this !== self::Product;
    }
}
