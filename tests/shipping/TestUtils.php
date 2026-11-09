<?php

namespace Lunar\Tests\Shipping;

use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Shipping\Models\ShippingExclusionList;
use Lunar\Shipping\Models\ShippingZone;

trait TestUtils
{
    public function createCart($currency = null, $price = 100, $quantity = 1, $calculate = true)
    {
        if (! $currency) {
            $currency = Currency::factory()->create([
                'default' => true,
            ]);
        }

        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
        ]);

        $purchasable = ProductVariant::factory()->create();
        $purchasable->stock = 100;

        Price::factory()->create([
            'price' => $price,
            'min_quantity' => 1,
            'currency_id' => $currency->id,
            'priceable_type' => $purchasable->getMorphClass(),
            'priceable_id' => $purchasable->id,
        ]);

        $cart->lines()->create([
            'purchasable_type' => $purchasable->getMorphClass(),
            'purchasable_id' => $purchasable->id,
            'quantity' => $quantity,
        ]);

        expect($cart->total)->toBeNull()
            ->and($cart->taxTotal)->toBeNull()
            ->and($cart->subTotal)->toBeNull();

        return $calculate ? $cart->calculate() : $cart;
    }

    /**
     * Build a calculated cart from variant lines.
     *
     * @param  list<array{price: int, variant?: array<string, mixed>, meta?: array<string, mixed>|null, quantity?: int}>  $lines
     * @return array{cart: Cart, variants: list<ProductVariant>}
     */
    public function cartWithVariantLines($currency, array $lines): array
    {
        $cart = Cart::factory()->create([
            'currency_id' => $currency->id,
        ]);

        $variants = [];

        foreach ($lines as $line) {
            $variant = ProductVariant::factory()->create($line['variant'] ?? []);
            $variants[] = $variant;

            Price::factory()->create([
                'price' => $line['price'],
                'min_quantity' => 1,
                'currency_id' => $currency->id,
                'priceable_type' => $variant->getMorphClass(),
                'priceable_id' => $variant->id,
            ]);

            $cart->lines()->create([
                'purchasable_type' => $variant->getMorphClass(),
                'purchasable_id' => $variant->id,
                'quantity' => $line['quantity'] ?? 1,
                'meta' => $line['meta'] ?? null,
            ]);
        }

        return [
            'cart' => $cart->calculate(),
            'variants' => $variants,
        ];
    }

    /**
     * @param  list<int>  $productIds
     */
    public function excludeProductsFromZone(ShippingZone $zone, array $productIds): void
    {
        $list = ShippingExclusionList::query()->create([
            'name' => 'Excluded products '.uniqid(),
        ]);

        foreach ($productIds as $productId) {
            $list->exclusions()->create([
                'purchasable_type' => Product::morphName(),
                'purchasable_id' => $productId,
            ]);
        }

        $zone->shippingExclusions()->attach($list);
    }
}
