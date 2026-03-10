<?php

namespace Lunar\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lunar\Base\BaseModel;
use Lunar\Base\Casts\Price;
use Lunar\Base\Casts\TaxBreakdown;
use Lunar\Base\Traits\HasMacros;
use Lunar\Base\Traits\LogsActivity;
use Lunar\Database\Factories\OrderLineFactory;
use Lunar\DataTypes\Price as PriceDataType;

/**
 * @property int $id
 * @property int $order_id
 * @property string $purchasable_type
 * @property int $purchasable_id
 * @property string $type
 * @property string $description
 * @property ?string $option
 * @property string $identifier
 * @property int $unit_price
 * @property int $unit_quantity
 * @property int $quantity
 * @property int $sub_total
 * @property int $discount_total
 * @property array $tax_breakdown
 * @property int $tax_total
 * @property int $total
 * @property ?string $notes
 * @property ?array $meta
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class OrderLine extends BaseModel implements Contracts\OrderLine
{
    use HasFactory;
    use HasMacros;
    use LogsActivity;

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return OrderLineFactory::new();
    }

    /**
     * Define which attributes should be
     * protected from mass assignment.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'unit_quantity' => 'integer',
        'quantity' => 'integer',
        'meta' => AsArrayObject::class,
        'tax_breakdown' => TaxBreakdown::class,
        'unit_price' => Price::class,
        'sub_total' => Price::class,
        'tax_total' => Price::class,
        'discount_total' => Price::class,
        'total' => Price::class,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::modelClass());
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    public function currency(): HasOneThrough
    {
        return $this->hasOneThrough(
            Currency::modelClass(),
            Order::modelClass(),
            'id',
            'code',
            'order_id',
            'currency_code'
        );
    }

    /**
     * Get the unit price excluding tax before coupon discount.
     */
    protected function unitPriceWithoutCoupon(): Attribute
    {
        return Attribute::make(
            get: function () {
                $breakdown = $this->order->discount_breakdown->whereNotNull('discount.coupon')->first();

                $taxTotal = 0;

                if (! config('lunar.pricing.stored_inclusive_of_tax', false)) {
                    $taxTotal = $this->tax_total->value;
                }

                // If no coupon then return the original unit price
                if (! $breakdown) {
                    return new PriceDataType((int) (($this->total->value - $taxTotal) / $this->quantity), $this->order->currency, 1);
                }

                $percentage = $breakdown->discount->data->percentage;

                // We need to calculate the original price without the percentage discount applied.
                $value = ($this->total->value - $taxTotal) / (1 - ($percentage / 100)) / $this->quantity;

                return new PriceDataType((int) $value, $this->order->currency);
            },
        );
    }

    /**
     * Get the unit price including tax before coupon discount.
     */
    protected function unitPriceWithoutCouponIncTax(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (config('lunar.pricing.stored_inclusive_of_tax', false)) {
                    return $this->unit_price_without_coupon;
                }

                $unitPriceExcTax = $this->unit_price_without_coupon->value;
                $unitPriceIncTax = $unitPriceExcTax * (1 + $this->tax_rate);

                return new PriceDataType((int) $unitPriceIncTax, $this->order->currency);
            },
        );
    }

    /**
     * Get the unit price including tax before coupon discount.
     */
    protected function priceWithoutCouponIncTax(): Attribute
    {
        return Attribute::make(
            get: function () {
                $priceExcTax = $this->unit_price_without_coupon->value * $this->quantity;

                if (config('lunar.pricing.stored_inclusive_of_tax', false)) {
                    return new PriceDataType((int) ($priceExcTax), $this->order->currency);
                }

                $priceIncTax = $priceExcTax * (1 + $this->tax_rate);

                return new PriceDataType((int) $priceIncTax, $this->order->currency);
            },
        );
    }

    protected function taxRate(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->tax_breakdown?->amounts?->first()?->percentage / 100 ?? 0;
            }
        );
    }
}
