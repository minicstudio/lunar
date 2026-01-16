<?php

namespace Lunar\Shipping\Drivers\ShippingMethods;

use Cartalyst\Converter\Laravel\Facades\Converter;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\Pricing;
use Lunar\Models\Product;
use Lunar\Shipping\DataTransferObjects\ShippingOptionRequest;
use Lunar\Shipping\Interfaces\ShippingRateInterface;
use Lunar\Shipping\Models\ShippingRate;

class ShipBy implements ShippingRateInterface
{
    /**
     * The shipping rate for context.
     */
    public ShippingRate $shippingRate;

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Ship By';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return 'Offer a set price to ship per order total or per line total.';
    }

    public function resolve(ShippingOptionRequest $shippingOptionRequest): ?ShippingOption
    {
        $shippingRate = $shippingOptionRequest->shippingRate;
        $shippingMethod = $shippingRate->shippingMethod;
        $shippingZone = $shippingRate->shippingZone;
        $data = $shippingMethod->data;
        $cart = $shippingOptionRequest->cart;
        $customerGroups = collect([]);

        if ($user = $cart->user) {
            $customerGroups = $user->customers->pluck('customerGroups')->flatten();
        }

        // Use discounted subtotal instead of base subtotal for shipping calculations
        // This ensures free shipping thresholds use discounted prices (excluding coupon codes)
        $subTotal = $cart->lines->sum(function ($line) {
            // Use subTotalDiscounted if available (includes automatic discounts)
            // Fall back to subTotal if subTotalDiscounted is not set
            return $line->subTotalDiscountedWithoutCoupon?->value ?? $line->subTotal?->value;
        });

        // Check allowed customer types for this shipping method
        $address = $cart->shippingAddress ?? $cart->address ?? null;
        if ($address && method_exists($shippingMethod, 'customerTypes')) {
            $customerTypeId = $address->address_customer_type_id ?? null;
            if ($customerTypeId && !$shippingMethod->customerTypes->pluck('id')->contains($customerTypeId)) {
                return null;
            }
        }

        // Do we have any products in our exclusions list?
        // If so, we do not want to return this option regardless.
        $productIds = $cart->lines->load('purchasable')->pluck('purchasable.product_id');

        $hasExclusions = $shippingZone->shippingExclusions()
            ->whereHas('exclusions', function ($query) use ($productIds) {
                $query->wherePurchasableType(Product::morphName())
                    ->whereIn('purchasable_id', $productIds);
            })->exists();

        if ($hasExclusions) {
            return null;
        }

        $chargeBy = $data['charge_by'] ?? null;

        if (! $chargeBy) {
            $chargeBy = 'cart_total';
        }

        // Calculate total weight for all cart lines
        $totalWeight = $cart->lines->sum(function ($line) {
            $weightUnit = $line->purchasable->weight_unit ?: 'kg';

            $unitWeightKg = Converter::from("weight.{$weightUnit}")
                ->to('weight.kg')
                ->value($line->purchasable->weight_value)
                ->convert()
                ->getValue();

            return $unitWeightKg * $line->quantity;
        });

        $tier = $subTotal;

        if ($chargeBy == 'weight') {
            $tier = $totalWeight;
        }

        // if locker then max weight check: max 20 kg
        if (! empty($cart->meta['shippingType']) && $cart->meta['shippingType'] === 'locker' && $totalWeight > 20) {
            return null;
        }

        // Do we have a suitable tier price?
        $pricing = Pricing::for($shippingRate)->customerGroups($customerGroups)->qty($tier)->get();

        $prices = $pricing->priceBreaks;

        // If there are customer group prices, they need to take priority.
        if (! $pricing->customerGroupPrices->isEmpty()) {
            $prices = $pricing->customerGroupPrices;
        }

        $matched = $prices->filter(function ($price) use ($tier) {
            $min = $price->min_quantity;
            $max = $price->max_quantity;
            if ($max) {
                return $tier >= $min && $tier < $max;
            }
            return $tier >= $min;
        })->sortByDesc('min_quantity')->first();

        // if there is no matched price, check if the tier exceeds the last break's max_quantity
        // if not, fall back to base price
        if (! $matched) {
            $lastBreak = $prices->sortByDesc('min_quantity')->first();

            if ($lastBreak && $lastBreak->max_quantity && $tier >= $lastBreak->max_quantity) {
                return null;
            }

            $matched = $pricing->base;
        }

        if (! $matched) {
            return null;
        }

        $price = $matched->price;

        return new ShippingOption(
            name: $shippingMethod->name,
            description: $shippingMethod->description,
            identifier: $shippingRate->getIdentifier(),
            price: $price,
            taxClass: $shippingRate->getTaxClass(),
            taxReference: $shippingRate->getTaxReference(),
            option: $shippingZone->name,
            meta: ['shipping_zone' => $shippingZone->name]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function on(ShippingRate $shippingRate): self
    {
        $this->shippingRate = $shippingRate;

        return $this;
    }
}
