<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Lunar\Shipping\DataTransferObjects\ShippingOptionRequest;
use Lunar\Shipping\Drivers\ShippingMethods\Collection;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Tests\Shipping\TestCase;
use Lunar\Tests\Shipping\TestUtils;

uses(TestCase::class)->group('shipping', 'shipping-driver', 'shipping-driver-collection');

uses(RefreshDatabase::class);
uses(TestUtils::class);

test('can get free shipping', function () {
    $currency = Currency::factory()->create([
        'default' => true,
    ]);

    TaxClass::factory()->create([
        'default' => true,
    ]);

    $shippingZone = ShippingZone::factory()->create([
        'type' => 'countries',
    ]);

    $shippingMethod = ShippingMethod::factory()->create([
        'driver' => 'free-shipping',
        'data' => [],
    ]);

    $shippingRate = ShippingRate::factory()->create([
        'shipping_method_id' => $shippingMethod->id,
        'shipping_zone_id' => $shippingZone->id,
    ]);

    $cart = $this->createCart($currency, 500);

    $driver = new Collection;

    $request = new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate
    );

    $shippingOption = $driver->resolve($request);

    expect($shippingOption)->toBeInstanceOf(ShippingOption::class);

    expect($shippingOption->price->value)->toEqual(0);
});

test('a preorder line does not mark collection as pending shipping', function () {
    $currency = Currency::factory()->create([
        'default' => true,
    ]);

    TaxClass::factory()->create([
        'default' => true,
    ]);

    $shippingZone = ShippingZone::factory()->create([
        'type' => 'countries',
    ]);

    $shippingMethod = ShippingMethod::factory()->create([
        'driver' => 'collection',
    ]);

    $shippingRate = ShippingRate::factory()->create([
        'shipping_method_id' => $shippingMethod->id,
        'shipping_zone_id' => $shippingZone->id,
    ]);

    ['cart' => $cart] = $this->cartWithVariantLines($currency, [
        ['price' => 500, 'variant' => ['purchasable' => 'preorder']],
    ]);

    $shippingOption = (new Collection)->resolve(new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate,
    ));

    expect($shippingOption)->toBeInstanceOf(ShippingOption::class)
        ->and($shippingOption->price->value)->toEqual(0)
        ->and($shippingOption->meta)->not->toHaveKey('preorder_shipping_pending');
});

test('an excluded preorder product does not hide collection while another line remains', function () {
    $currency = Currency::factory()->create([
        'default' => true,
    ]);

    TaxClass::factory()->create([
        'default' => true,
    ]);

    $shippingZone = ShippingZone::factory()->create([
        'type' => 'countries',
    ]);

    $shippingMethod = ShippingMethod::factory()->create([
        'driver' => 'collection',
    ]);

    $shippingRate = ShippingRate::factory()->create([
        'shipping_method_id' => $shippingMethod->id,
        'shipping_zone_id' => $shippingZone->id,
    ]);

    ['cart' => $offeredCart, 'variants' => $offeredVariants] = $this->cartWithVariantLines($currency, [
        ['price' => 500],
        ['price' => 500, 'variant' => ['purchasable' => 'preorder']],
    ]);

    $this->excludeProductsFromZone($shippingZone, [$offeredVariants[1]->product_id]);

    $offered = (new Collection)->resolve(new ShippingOptionRequest(
        cart: $offeredCart,
        shippingRate: $shippingRate,
    ));

    expect($offered)->toBeInstanceOf(ShippingOption::class)
        ->and($offered->price->value)->toEqual(0)
        ->and($offered->meta)->not->toHaveKey('preorder_shipping_pending');

    ['cart' => $hiddenCart, 'variants' => $hiddenVariants] = $this->cartWithVariantLines($currency, [
        ['price' => 500],
        ['price' => 500, 'variant' => ['purchasable' => 'preorder']],
    ]);

    $this->excludeProductsFromZone($shippingZone, [$hiddenVariants[0]->product_id]);

    $hidden = (new Collection)->resolve(new ShippingOptionRequest(
        cart: $hiddenCart,
        shippingRate: $shippingRate,
    ));

    expect($hidden)->toBeNull();
});
