<?php

namespace Lunar\Actions\Orders;

use Lunar\Models\OrderLine;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRateAmount;

/**
 * Tax class recorded on a shipping line.
 *
 * The breakdown stores `tax_rate_{id}` plus the percentage. That pair is the class
 * the shipping was taxed with. A line without a matching breakdown uses the default class.
 */
final class ShippingLineTaxClass
{
    public static function resolve(?OrderLine $shippingLine): TaxClass
    {
        return self::matched($shippingLine)
            ?? TaxClass::getDefault()
            ?? TaxClass::query()->firstOrFail();
    }

    /**
     * The class recorded on the line, or null when that rate can no longer be found.
     */
    public static function matched(?OrderLine $shippingLine): ?TaxClass
    {
        foreach ($shippingLine?->tax_breakdown?->amounts ?? [] as $amount) {
            if (! preg_match('/^tax_rate_(\d+)$/', (string) $amount->identifier, $matches)) {
                continue;
            }

            $amounts = TaxRateAmount::query()
                ->where('tax_rate_id', (int) $matches[1])
                ->whereNotNull('tax_class_id')
                ->orderBy('id')
                ->get();

            $matched = $amounts->first(function (TaxRateAmount $rateAmount) use ($amount): bool {
                return abs((float) $rateAmount->percentage - (float) $amount->percentage) < 0.001;
            });

            if ($matched?->taxClass) {
                return $matched->taxClass;
            }

            if ($amounts->pluck('tax_class_id')->unique()->count() === 1) {
                return $amounts->first()?->taxClass;
            }
        }

        return null;
    }
}
