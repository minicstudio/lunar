<?php

namespace Lunar\Tests\ProductImport\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Models\Discount;
use Lunar\ProductImport\Data\ColumnFormat;
use Lunar\ProductImport\Models\ProductImport;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Sheets with discounts and the store's existing discounts, for the tests of
 * discounts.
 */
class Discounts
{
    /**
     * A sheet with SKU, name, price, discount amount and percent, and the
     * sale start and end, the dates read as days (`Y-m-d`).
     *
     * @param  array<int, list<?string>>  $rows  Data rows keyed by row number (header is row 1).
     */
    public static function sheet(ProductImport $import, int $productTypeId, array $rows, string $name = 'Outlet'): ProductImportSheet
    {
        $sheet = ImportBuilder::sheet($import, $name, [1 => ['Cod', 'Denumire', 'Pret', 'Reducere', 'Reducere %', 'De la', 'Pana la']] + $rows);

        return ImportBuilder::map($sheet, [
            0 => 'variant.sku', 1 => ['product.name', 'ro'], 2 => 'variant.price', 3 => 'variant.discount_amount', 4 => 'variant.discount_percent',
            5 => ['variant.sale_starts', null, ColumnFormat::date('Y-m-d')], 6 => ['variant.sale_ends', null, ColumnFormat::date('Y-m-d')],
        ], $productTypeId);
    }

    /**
     * An automatic percent discount of the store, limited to the given
     * products or variants.
     *
     * @param  list<Model>  $limitedTo
     */
    public static function percent(string $name, int|float $percent, string $startsAt, ?string $endsAt, array $limitedTo = []): Discount
    {
        return self::existing($name, ['fixed_value' => false, 'percentage' => $percent], $startsAt, $endsAt, $limitedTo);
    }

    /**
     * An automatic fixed amount discount of the store, in minor units of the
     * given currency, limited to the given products or variants.
     *
     * @param  list<Model>  $limitedTo
     */
    public static function amount(string $name, int $amount, string $startsAt, ?string $endsAt, array $limitedTo = [], string $currency = 'RON'): Discount
    {
        return self::existing($name, ['fixed_value' => true, 'fixed_values' => [$currency => $amount]], $startsAt, $endsAt, $limitedTo);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<Model>  $limitedTo
     */
    protected static function existing(string $name, array $data, string $startsAt, ?string $endsAt, array $limitedTo): Discount
    {
        $discount = Discount::factory()->create([
            'name' => $name,
            'handle' => Str::slug($name),
            'type' => AdvancedAmountOff::class,
            'data' => $data,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        foreach ($limitedTo as $purchasable) {
            $discount->discountables()->create([
                'discountable_type' => $purchasable->getMorphClass(),
                'discountable_id' => $purchasable->getKey(),
                'type' => 'limitation',
            ]);
        }

        return $discount;
    }
}
