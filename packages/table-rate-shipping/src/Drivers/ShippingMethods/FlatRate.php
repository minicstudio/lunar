<?php

namespace Lunar\Shipping\Drivers\ShippingMethods;

use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\Pricing;
use Lunar\Models\Product;
use Lunar\Shipping\DataTransferObjects\ShippingOptionRequest;
use Lunar\Shipping\Interfaces\ShippingRateInterface;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\ShippingBasis;

class FlatRate implements ShippingRateInterface
{
    /**
     * The shipping method for context.
     */
    public ShippingRate $shippingRate;

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Flat Rate Shipping';
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
                name: $shippingMethod->name ?: $this->name(),
                description: $shippingMethod->description ?: $this->description(),
                identifier: $shippingRate->getIdentifier(),
                price: new Price(0, $cart->currency, 1),
                taxClass: $shippingRate->getTaxClass(),
                taxReference: $shippingRate->getTaxReference(),
                option: $shippingZone->name,
                meta: [
                    'shipping_zone' => $shippingZone->name,
                    'preorder_shipping_pending' => true,
                ],
            );
        }

        $subTotal = $basisLines->sum('subTotal.value');

        $pricing = Pricing::for($shippingRate)->qty($subTotal)->get();

        if (! $pricing->matched) {
            return null;
        }

        return new ShippingOption(
            name: $shippingMethod->name ?: $this->name(),
            description: $shippingMethod->description ?: $this->description(),
            identifier: $shippingRate->getIdentifier(),
            price: $pricing->matched->price,
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
