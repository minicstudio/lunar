<?php

namespace Lunar\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\Attribute as EloquentAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Lunar\Base\BaseModel;
use Lunar\Base\Casts\DiscountBreakdown;
use Lunar\Base\Casts\Price;
use Lunar\Base\Casts\ShippingBreakdown;
use Lunar\Base\Casts\TaxBreakdown;
use Lunar\Base\Traits\HasMacros;
use Lunar\Base\Traits\HasTags;
use Lunar\Base\Traits\LogsActivity;
use Lunar\Base\Traits\Searchable;
use Lunar\Database\Factories\OrderFactory;
use Lunar\DataTypes\Price as PriceDataType;
use Lunar\Exceptions\UnsupportedWeightUnitException;

/**
 * @property int $id
 * @property ?int $customer_id
 * @property ?int $user_id
 * @property int $channel_id
 * @property bool $new_customer
 * @property string $status
 * @property ?string $reference
 * @property ?string $customer_reference
 * @property int $sub_total
 * @property int $discount_total
 * @property array $discount_breakdown
 * @property array $shipping_breakdown
 * @property array $tax_breakdown
 * @property int $tax_total
 * @property int $total
 * @property ?string $notes
 * @property string $currency_code
 * @property ?string $compare_currency_code
 * @property float $exchange_rate
 * @property ?\Illuminate\Support\Carbon $placed_at
 * @property ?array $meta
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class Order extends BaseModel implements Contracts\Order
{
    use HasFactory,
        HasMacros,
        HasTags,
        LogsActivity,
        Searchable;

    /**
     * {@inheritDoc}
     */
    protected $casts = [
        'tax_breakdown' => TaxBreakdown::class,
        'meta' => AsArrayObject::class,
        'placed_at' => 'datetime',
        'sub_total' => Price::class,
        'discount_total' => Price::class,
        'discount_breakdown' => DiscountBreakdown::class,
        'shipping_breakdown' => ShippingBreakdown::class,
        'tax_total' => Price::class,
        'total' => Price::class,
        'shipping_total' => Price::class,
        'new_customer' => 'boolean',
    ];

    /**
     * {@inheritDoc}
     */
    protected $guarded = [];

    protected static function newFactory()
    {
        return OrderFactory::new();
    }

    public function getStatusLabelAttribute(): string
    {
        $statuses = config('lunar.orders.statuses');

        return $statuses[$this->status]['label'] ?? $this->status;
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::modelClass());
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::modelClass());
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::modelClass());
    }

    public function physicalLines(): HasMany
    {
        return $this->lines()->whereType('physical');
    }

    public function digitalLines(): HasMany
    {
        return $this->lines()->whereType('digital');
    }

    public function shippingLines(): HasMany
    {
        return $this->lines()->whereType('shipping');
    }

    public function productLines(): HasMany
    {
        return $this->lines()->where('type', '!=', 'shipping');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::modelClass(), 'currency_code', 'code');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::modelClass(), 'order_id');
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::modelClass(), 'order_id')->whereType('shipping');
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::modelClass(), 'order_id')->whereType('billing');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::modelClass())->orderBy('created_at', 'desc');
    }

    public function captures(): HasMany
    {
        return $this->transactions()->whereType('capture');
    }

    public function intents(): HasMany
    {
        return $this->transactions()->whereType('intent');
    }

    public function refunds(): HasMany
    {
        return $this->transactions()->whereType('refund');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::modelClass());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            config('auth.providers.users.model')
        );
    }

    public function isDraft(): bool
    {
        return ! $this->isPlaced();
    }

    public function isPlaced(): bool
    {
        return ! blank($this->placed_at);
    }

    public static function getDefaultLogExcept(): array
    {
        return [
            'status',
        ];
    }

    /**
     * Sum of coupon discounts.
     */
    protected function couponTotal(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: function () {
                $total = 0;

                $couponBreakdowns = $this->discount_breakdown->filter(function ($breakdown) {
                    return $breakdown->discount->coupon !== null;
                });

                foreach ($couponBreakdowns as $breakdown) {
                    $percentage = $breakdown->discount->data->percentage;

                    foreach ($breakdown->lines as $lineData) {
                        $orderLine = $lineData->line;

                        if ($orderLine) {
                            $unitPriceExcTax = $orderLine->unit_price_without_coupon->value;

                            $discountAmount = ($unitPriceExcTax * $lineData->quantity) * ($percentage / 100);

                            $discountAmountIncTax = $discountAmount;

                            if (! config('lunar.pricing.stored_inclusive_of_tax', false)) {
                                $discountAmountIncTax = $discountAmount * (1 + $orderLine->tax_rate);
                            }

                            $total += (int) $discountAmountIncTax;
                        }
                    }
                }

                return new PriceDataType((int) $total, $this->currency, 1);
            },
        );
    }

    /**
     * Sum of percentage discounts without coupons
     */
    protected function discountTotalWithoutCoupon(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: function () {
                $total = $this->discount_breakdown->filter(function ($breakdown) {
                    return $breakdown->discount->coupon === null;
                })->sum('total.value');

                return new PriceDataType($total, $this->currency, 1);
            },
        );
    }

    /**
     * Get the subtotal discounted without coupon including tax.
     */
    protected function subTotalDiscountedWithoutCouponIncTax(): EloquentAttribute
    {
        return EloquentAttribute::make(
            get: function () {
                if (config('lunar.pricing.stored_inclusive_of_tax', false)) {
                    return new PriceDataType($this->sub_total->value - $this->discount_total_without_coupon->value, $this->currency, 1);
                }

                $total = $this->productLines->sum(function ($line) {
                    return $line->price_without_coupon_inc_tax->value;
                });

                return new PriceDataType((int) $total, $this->currency, 1);
            },
        );
    }

    /**
     * Get the total weight in kg of the package based on order items.
     */
    public function getPackageWeightAttribute(): float
    {
        return $this->productLines->sum(function ($item) {
            // in case of weight in grams, convert to kg and if nor g nor kg then throw exception
            switch ($item->purchasable->weight_unit) {
                case 'kg':
                    return $item->quantity * $item->purchasable->weight_value;
                case 'g':
                    // convert grams to kg
                    return ($item->quantity * $item->purchasable->weight_value) / 1000;
                case 'lbs':
                    // convert pounds to kg (1 lb = 0.453592 kg)
                    return ($item->quantity * $item->purchasable->weight_value) * 0.453592;
                default:
                    // if the weight unit is not recognized, throw an exception
                    throw new UnsupportedWeightUnitException('Unsupported weight unit: '.$item->purchasable->weight_unit.'. Please change the weight unit of the product to kg, g, or lbs.');
            }
        });
    }

    /**
     * Get the applied coupon.
     */
    public function getAppliedCouponAttribute(): ?Discount
    {
        return Discount::whereIn(
            'id',
            collect($this->discount_breakdown)
                ->pluck('discount_id')
                ->unique()
        )
            ->whereNotNull('coupon')
            ->first();
    }

    /**
     * Get the status history activities for the order.
     */
    public function getActivitiesByStatuses(array|Collection $statuses): Collection
    {
        return $this->activities()
            ->where('description', 'status-update')
            ->whereIn('properties->new', $statuses)
            ->latest('created_at')
            ->get();
    }
}
