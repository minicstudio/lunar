<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Lunar\Shipping\DataTransferObjects\ShippingOptionRequest;
use Lunar\Shipping\Drivers\ShippingMethods\FlatRate;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Tests\Shipping\TestCase;
use Lunar\Tests\Shipping\TestUtils;

uses(TestCase::class)->group('shipping', 'shipping-driver', 'shipping-driver-flatrate');

uses(RefreshDatabase::class);
uses(TestUtils::class);

test('can get flat rate shipping', function () {
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
        'driver' => 'flat-rate',
        'data' => [
            'minimum_spend' => [
                "{$currency->code}" => 200,
            ],
        ],
    ]);

    $shippingRate = ShippingRate::factory()
        ->create([
            'shipping_method_id' => $shippingMethod->id,
            'shipping_zone_id' => $shippingZone->id,
        ]);

    $shippingRate->prices()->createMany([
        [
            'price' => 600,
            'min_quantity' => 1,
            'currency_id' => $currency->id,
        ],
    ]);

    $cart = $this->createCart($currency, 500);

    $driver = new FlatRate;

    $request = new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate
    );

    $shippingOption = $driver->resolve($request);

    expect($shippingOption)->toBeInstanceOf(ShippingOption::class);

    expect($shippingOption->price->value)->toEqual(600);
});

test('a mixed cart flat rate uses only the non-preorder subtotal', function () {
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
        'driver' => 'flat-rate',
    ]);

    $shippingRate = ShippingRate::factory()->create([
        'shipping_method_id' => $shippingMethod->id,
        'shipping_zone_id' => $shippingZone->id,
    ]);

    $shippingRate->prices()->createMany([
        [
            'price' => 600,
            'min_quantity' => 1,
            'currency_id' => $currency->id,
        ],
        [
            'price' => 200,
            'min_quantity' => 800,
            'currency_id' => $currency->id,
        ],
    ]);

    ['cart' => $cart] = $this->cartWithVariantLines($currency, [
        ['price' => 500],
        ['price' => 10000, 'variant' => ['purchasable' => 'preorder']],
    ]);

    $shippingOption = (new FlatRate)->resolve(new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate,
    ));

    expect($shippingOption)->toBeInstanceOf(ShippingOption::class)
        ->and($shippingOption->price->value)->toEqual(600)
        ->and($shippingOption->meta)->not->toHaveKey('preorder_shipping_pending');
});

test('a preorder-only cart is offered flat rate at price 0 with the pending marker', function () {
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
        'driver' => 'flat-rate',
    ]);

    $shippingRate = ShippingRate::factory()->create([
        'shipping_method_id' => $shippingMethod->id,
        'shipping_zone_id' => $shippingZone->id,
    ]);

    $shippingRate->prices()->create([
        'price' => 600,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
    ]);

    ['cart' => $cart] = $this->cartWithVariantLines($currency, [
        ['price' => 10000, 'variant' => ['purchasable' => 'preorder']],
    ]);

    $shippingOption = (new FlatRate)->resolve(new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate,
    ));

    expect($shippingOption)->toBeInstanceOf(ShippingOption::class)
        ->and($shippingOption->price->value)->toEqual(0)
        ->and($shippingOption->meta['preorder_shipping_pending'])->toBeTrue();
});
