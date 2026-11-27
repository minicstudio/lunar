<?php

namespace Lunar\ProductImport\Enums;

/**
 * The variant field rows are matched to existing variants by.
 */
enum MatchKey: string
{
    case Sku = 'sku';
    case Ean = 'ean';

    /**
     * The mapping target holding the key.
     */
    public function target(): TargetField
    {
        return match ($this) {
            self::Sku => TargetField::VariantSku,
            self::Ean => TargetField::VariantEan,
        };
    }

    /**
     * The variant column holding the key.
     */
    public function column(): string
    {
        return match ($this) {
            self::Sku => 'sku',
            self::Ean => 'ean',
        };
    }
}
