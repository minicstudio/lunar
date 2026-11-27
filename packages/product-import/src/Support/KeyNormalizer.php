<?php

namespace Lunar\ProductImport\Support;

use Illuminate\Support\Str;

/**
 * Compares values without letter case, accents, and repeated or surrounding
 * spaces.
 */
class KeyNormalizer
{
    /**
     * The comparison key of a value.
     */
    public static function key(?string $value): string
    {
        return (string) Str::of((string) $value)->ascii()->lower()->squish();
    }
}
