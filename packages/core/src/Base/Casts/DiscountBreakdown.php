<?php

namespace Lunar\Base\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Lunar\DataTypes\Price;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\OrderLine;

class DiscountBreakdown implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return \Illuminate\Support\Collection
     */
    public function get($model, $key, $value, $attributes)
    {
        $currency = $model->currency ?: Currency::getDefault();

        return collect(
            json_decode($value, false)
        )->map(function ($breakdown) use ($currency) {
            $breakdown->total = new Price($breakdown->total, $currency, 1);
            $breakdown->lines = collect($breakdown->lines)->map(function ($line) use ($currency) {
                return (object) [
                    'quantity' => $line->qty,
                    'line' => OrderLine::find($line->id),
                    'amount' => isset($line->amount) && ! is_null($line->amount)
                        ? new Price($line->amount, $currency, 1)
                        : null,
                ];
            });

            return $breakdown;
        });
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  \Illuminate\Support\Collection  $value
     * @param  array  $attributes
     * @return array
     */
    public function set($model, $key, $value, $attributes)
    {
        return [
            $key => collect($value)->map(function ($discountLine) {
                return [
                    'discount_id' => $discountLine->discount_id,
                    'lines' => $discountLine->lines->map(function ($orderLine) {
                        return [
                            'id' => $orderLine->line->id,
                            'qty' => $orderLine->quantity,
                            'amount' => $orderLine->amount?->value,
                        ];
                    })->values(),
                    'total' => $discountLine->total->value,

                    'discount' => Discount::find($discountLine->discount_id),
                ];
            })->toJson(),
        ];
    }
}
