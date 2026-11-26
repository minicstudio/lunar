<?php

namespace Lunar\ProductImport\Taxonomy;

use Lunar\ProductImport\Normalizing\RowDiscount;
use Lunar\ProductImport\Support\KeyNormalizer;

/**
 * The normalized keys values are collected and decided under.
 */
class ValueKeys
{
    /**
     * The key of a category path.
     *
     * @param  list<string>  $path
     */
    public static function category(array $path): string
    {
        return implode(' > ', array_map(fn (string $level): string => KeyNormalizer::key($level), $path));
    }

    /**
     * The key of a brand or an option value.
     */
    public static function name(string $value): string
    {
        return KeyNormalizer::key($value);
    }

    /**
     * The option key of an option named in cells: `name:<normalized name>`.
     */
    public static function namedOption(string $name): string
    {
        return 'name:'.KeyNormalizer::key($name);
    }

    /**
     * The option key under which a product type's properties are collected.
     */
    public static function propertyType(?int $productTypeId): string
    {
        return 'type:'.($productTypeId ?? 0);
    }

    /**
     * The key of a discount: its kind, value, start, and end.
     */
    public static function discount(RowDiscount $discount): string
    {
        return $discount->key();
    }
}
