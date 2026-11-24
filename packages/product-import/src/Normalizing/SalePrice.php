<?php

namespace Lunar\ProductImport\Normalizing;

use Carbon\CarbonImmutable;
use Lunar\ProductImport\Data\NormalizedRow;
use Lunar\ProductImport\Enums\TargetField;

/**
 * The price a row writes, from its regular price and its sale: a sale price,
 * or the price minus a discount amount or percent, rounded to the minor
 * unit. A sale applies when today is within its dates (an empty date is
 * open) and it is lower than the regular price; then the base price is the
 * sale price and the compare price the regular price. Otherwise the base
 * price is the regular price and the compare price is cleared.
 */
final readonly class SalePrice
{
    /**
     * @param  ?int  $price  The base price in minor units, or null when the row writes no price.
     * @param  ?int  $comparePrice  The compare price in minor units, or null to clear it.
     * @param  ?int  $salePrice  The sale price, also when it does not apply.
     * @param  bool  $applies  Whether the sale applies today.
     * @param  ?string  $endsAt  The sale's end, when it has one.
     */
    public function __construct(
        public ?int $price,
        public ?int $comparePrice,
        public ?int $salePrice,
        public bool $applies,
        public ?string $endsAt,
    ) {}

    /**
     * The prices of a row on the given day (today by default).
     */
    public static function for(NormalizedRow $values, ?CarbonImmutable $today = null): self
    {
        $regular = $values->get(TargetField::VariantPrice->value);
        $regular = is_int($regular) ? $regular : null;
        $sale = self::sale($values, $regular);

        if ($regular === null || $sale === null) {
            return new self($regular, null, $sale, false, null);
        }

        $today ??= CarbonImmutable::now();
        $starts = $values->get(TargetField::VariantSaleStarts->value);
        $ends = $values->get(TargetField::VariantSaleEnds->value);

        $applies = $sale < $regular
            && ($starts === null || CarbonImmutable::parse((string) $starts)->lessThanOrEqualTo($today))
            && ($ends === null || CarbonImmutable::parse((string) $ends)->greaterThanOrEqualTo($today));

        return $applies
            ? new self($sale, $regular, $sale, true, $ends !== null ? (string) $ends : null)
            : new self($regular, null, $sale, false, $ends !== null ? (string) $ends : null);
    }

    /**
     * Whether the row describes a sale.
     */
    public function hasSale(): bool
    {
        return $this->salePrice !== null;
    }

    /**
     * The sale price in minor units: given directly, or from a discount.
     */
    protected static function sale(NormalizedRow $values, ?int $regular): ?int
    {
        $sale = $values->get(TargetField::VariantSalePrice->value);

        if (is_int($sale)) {
            return $sale;
        }

        $amount = $values->get(TargetField::VariantDiscountAmount->value);

        if ($regular !== null && is_int($amount) && $amount > 0) {
            return $regular - $amount;
        }

        $percent = $values->get(TargetField::VariantDiscountPercent->value);

        if ($regular !== null && is_numeric($percent) && (float) $percent > 0) {
            return (int) round($regular * (1 - (float) $percent / 100));
        }

        return null;
    }
}
