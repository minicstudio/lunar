<?php

namespace Lunar\Shipping\Drivers\ShippingMethods;

use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Product;
use Lunar\Shipping\DataTransferObjects\ShippingOptionRequest;
use Lunar\Shipping\Interfaces\ShippingRateInterface;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\ShippingBasis;

class FreeShipping implements ShippingRateInterface
{
    /**
     * The shipping method for context.
     */
    public ShippingRate $shippingRate;

    /**
     * {@inheritDoc}
     */
    public function name(): string
    {
        return 'Free Shipping';
    }

    /**
     * {@inheritDoc}
     */
    public function description(): string
    {
        return 'Offer free shipping for your customers';
    }

    public function resolve(ShippingOptionRequest $shippingOptionRequest): ?ShippingOption
    {
        $shippingRate = $shippingOptionRequest->shippingRate;
        $shippingMethod = $shippingRate->shippingMethod;
        $shippingZone = $shippingRate->shippingZone;
        $data = $shippingMethod->data;
        $cart = $shippingOptionRequest->cart;

        $cart->lines->load('purchasable');

        $basisLines = ShippingBasis::lines($cart);

        // Do we have any products in our exclusions list?
        // If so, we do not want to return this option regardless.
        // Preorder lines are not part of the basis, so they cannot exclude the option.
        $productIds = $basisLines->pluck('purchasable.product_id');

        $hasExclusions = $shippingZone->shippingExclusions()
            ->whereHas('exclusions', function ($query) use ($productIds) {
                $query->wherePurchasableType(Product::morphName())
                    ->whereIn('purchasable_id', $productIds);
            })->exists();

        if ($hasExclusions) {
            return null;
        }

        if (ShippingBasis::isPreorderOnly($cart)) {
            return new ShippingOption(
                name: $shippingMethod->name,
                description: $shippingMethod->description,
                identifier: $shippingRate->getIdentifier(),
                price: new Price(0, $cart->currency, 1),
                taxClass: $shippingRate->getTaxClass(),
                option: $shippingZone->name,
                meta: [
                    'shipping_zone' => $shippingZone->name,
                    'preorder_shipping_pending' => true,
                ],
            );
        }

        $subTotal = $basisLines->sum(fn ($line) => $line->subTotal?->value ?? 0);

        if ($data['use_discount_amount'] ?? false) {
            $subTotal -= $basisLines->sum(fn ($line) => $line->discountTotal?->value ?? 0);
        }

        $minSpend = $data['minimum_spend'] ?? null;

        if (is_array($minSpend)) {
            $minSpend = $minSpend[$cart->currency->code] ?? null;
        }

        if (is_null($minSpend) || $minSpend > $subTotal) {
            return null;
        }

        return new ShippingOption(
            name: $shippingMethod->name,
            description: $shippingMethod->description,
            identifier: $shippingRate->getIdentifier(),
            price: new Price(0, $cart->currency, 1),
            taxClass: $shippingRate->getTaxClass(),
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
