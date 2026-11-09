<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\DataTypes\Price as DataTypesPrice;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Currency;
use Lunar\Models\TaxClass;
use Lunar\Shipping\DataTransferObjects\ShippingOptionRequest;
use Lunar\Shipping\Drivers\ShippingMethods\FreeShipping;
use Lunar\Shipping\Models\ShippingMethod;
use Lunar\Shipping\Models\ShippingRate;
use Lunar\Shipping\Models\ShippingZone;
use Lunar\Tests\Shipping\TestCase;
use Lunar\Tests\Shipping\TestUtils;

uses(TestCase::class)->group('shipping', 'shipping-driver', 'shipping-driver-freeshiping');

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
        'data' => [
            'minimum_spend' => [
                "{$currency->code}" => 500,
            ],
        ],
    ]);

    $shippingRate = ShippingRate::factory()
        ->create([
            'shipping_method_id' => $shippingMethod->id,
            'shipping_zone_id' => $shippingZone->id,
        ]);

    $cart = $this->createCart($currency, 500);

    $driver = new FreeShipping;

    $request = new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate
    );

    $shippingOption = $driver->resolve($request);

    expect($shippingOption)->toBeInstanceOf(ShippingOption::class);
});

test('cant get free shipping if minimum isnt met', function () {
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
        'data' => [
            'minimum_spend' => [
                "{$currency->code}" => 500,
            ],
        ],
    ]);

    $shippingRate = ShippingRate::factory()
        ->create([
            'shipping_method_id' => $shippingMethod->id,
            'shipping_zone_id' => $shippingZone->id,
        ]);

    $cart = $this->createCart($currency, 50);

    $driver = new FreeShipping;

    $request = new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate
    );

    $shippingOption = $driver->resolve($request);

    expect($shippingOption)->toBeNull();
});

test('cant get free shipping if currency isnt met', function () {
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
        'data' => [
            'minimum_spend' => [
                'FOO' => 500,
            ],
        ],
    ]);

    $shippingRate = ShippingRate::factory()
        ->create([
            'shipping_method_id' => $shippingMethod->id,
            'shipping_zone_id' => $shippingZone->id,
        ]);

    $cart = $this->createCart($currency, 10000);

    $driver = new FreeShipping;

    $request = new ShippingOptionRequest(
        shippingRate: $shippingRate,
        cart: $cart,
    );

    $shippingOption = $driver->resolve($request);

    expect($shippingOption)->toBeNull();
});

test('a preorder-only cart is offered free shipping at price 0 when the empty basis misses the minimum', function () {
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
        'data' => [
            'minimum_spend' => [
                "{$currency->code}" => 500,
            ],
        ],
    ]);

    $shippingRate = ShippingRate::factory()->create([
        'shipping_method_id' => $shippingMethod->id,
        'shipping_zone_id' => $shippingZone->id,
    ]);

    ['cart' => $cart] = $this->cartWithVariantLines($currency, [
        ['price' => 50, 'variant' => ['purchasable' => 'preorder']],
    ]);

    $shippingOption = (new FreeShipping)->resolve(new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate,
    ));

    expect($shippingOption)->toBeInstanceOf(ShippingOption::class)
        ->and($shippingOption->price->value)->toEqual(0)
        ->and($shippingOption->meta['preorder_shipping_pending'])->toBeTrue();
});

test('a mixed cart below the free shipping minimum on non-preorder spend is not offered', function () {
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
        'data' => [
            'minimum_spend' => [
                "{$currency->code}" => 500,
            ],
        ],
    ]);

    $shippingRate = ShippingRate::factory()->create([
        'shipping_method_id' => $shippingMethod->id,
        'shipping_zone_id' => $shippingZone->id,
    ]);

    ['cart' => $cart] = $this->cartWithVariantLines($currency, [
        ['price' => 50],
        ['price' => 10000, 'variant' => ['purchasable' => 'preorder']],
    ]);

    $shippingOption = (new FreeShipping)->resolve(new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate,
    ));

    expect($shippingOption)->toBeNull();
});

test('free shipping subtracts discounts only from the non-preorder lines', function () {
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
        'data' => [
            'minimum_spend' => [
                "{$currency->code}" => 500,
            ],
            'use_discount_amount' => true,
        ],
    ]);

    $shippingRate = ShippingRate::factory()->create([
        'shipping_method_id' => $shippingMethod->id,
        'shipping_zone_id' => $shippingZone->id,
    ]);

    ['cart' => $cart] = $this->cartWithVariantLines($currency, [
        ['price' => 600],
        ['price' => 10000, 'variant' => ['purchasable' => 'preorder']],
    ]);

    $preorderLine = $cart->lines->first(fn ($line) => $line->purchasable->isPreorder());
    $preorderLine->discountTotal = new DataTypesPrice(200, $currency, 1);
    $cart->discountTotal = new DataTypesPrice(200, $currency, 1);

    $shippingOption = (new FreeShipping)->resolve(new ShippingOptionRequest(
        cart: $cart,
        shippingRate: $shippingRate,
    ));

    expect($shippingOption)->toBeInstanceOf(ShippingOption::class)
        ->and($shippingOption->meta)->not->toHaveKey('preorder_shipping_pending');

    ['cart' => $discountedCart] = $this->cartWithVariantLines($currency, [
        ['price' => 600],
        ['price' => 100, 'variant' => ['purchasable' => 'preorder']],
    ]);

    $payableLine = $discountedCart->lines->first(fn ($line) => ! $line->purchasable->isPreorder());
    $payableLine->discountTotal = new DataTypesPrice(200, $currency, 1);
    $discountedCart->discountTotal = new DataTypesPrice(200, $currency, 1);

    $discountedOption = (new FreeShipping)->resolve(new ShippingOptionRequest(
        cart: $discountedCart,
        shippingRate: $shippingRate,
    ));

    expect($discountedOption)->toBeNull();
});
