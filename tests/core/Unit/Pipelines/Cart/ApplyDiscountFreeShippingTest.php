<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdownItem;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\TaxClass;
use Lunar\Pipelines\Cart\ApplyDiscountFreeShipping;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);

uses(RefreshDatabase::class);

/**
 * A cart whose shipping breakdown holds one option at the given price.
 */
function cartWithShippingBreakdown(int $price): Cart
{
    $cart = Cart::factory()->create([
        'currency_id' => Currency::factory()->create()->id,
    ]);

    CartAddress::factory()->create([
        'cart_id' => $cart->id,
        'type' => 'shipping',
    ]);

    $cart->shippingBreakdown = new ShippingBreakdown(collect([
        'COURIER' => new ShippingBreakdownItem(
            name: 'Courier',
            identifier: 'COURIER',
            price: new Price($price, $cart->currency, 1),
        ),
    ]));

    $cart->shippingSubTotal = new Price($price, $cart->currency, 1);
    $cart->shippingAddress->shippingSubTotal = new Price($price, $cart->currency, 1);
    $cart->shippingAddress->shippingTotal = new Price($price, $cart->currency, 1);

    return $cart;
}

test('waives every shipping breakdown item and reports the waived amount', function () {
    $cart = cartWithShippingBreakdown(1990);
    $cart->freeShippingDiscount = Discount::factory()->create();

    app(ApplyDiscountFreeShipping::class)->handle($cart, fn ($cart) => $cart);

    expect($cart->shippingDiscountTotal?->value)->toBe(1990)
        ->and($cart->shippingBreakdown->items->keys()->all())->toBe(['COURIER'])
        ->and($cart->shippingBreakdown->items->first()->name)->toBe('Courier')
        ->and($cart->shippingBreakdown->items->first()->price->value)->toBe(0)
        ->and($cart->shippingSubTotal->value)->toBe(0)
        ->and($cart->shippingAddress->shippingSubTotal->value)->toBe(0)
        ->and($cart->shippingAddress->shippingTotal->value)->toBe(0);
});

test('leaves shipping unchanged without a free shipping discount', function () {
    $cart = cartWithShippingBreakdown(1990);
    $cart->shippingDiscountTotal = new Price(1990, $cart->currency, 1);

    app(ApplyDiscountFreeShipping::class)->handle($cart, fn ($cart) => $cart);

    expect($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->shippingBreakdown->items->first()->price->value)->toBe(1990)
        ->and($cart->shippingSubTotal->value)->toBe(1990);
});

test('leaves an explicit shipping override unchanged', function () {
    $cart = cartWithShippingBreakdown(1200);
    $cart->freeShippingDiscount = Discount::factory()->create();
    $cart->shippingOptionOverride = new ShippingOption(
        name: 'Courier',
        description: 'Courier',
        identifier: 'COURIER',
        price: new Price(1200, $cart->currency, 1),
        taxClass: TaxClass::factory()->create(),
    );

    app(ApplyDiscountFreeShipping::class)->handle($cart, fn ($cart) => $cart);

    expect($cart->shippingDiscountTotal)->toBeNull()
        ->and($cart->shippingBreakdown->items->first()->price->value)->toBe(1200)
        ->and($cart->shippingSubTotal->value)->toBe(1200);
});

test('reports no waived amount for a 0 priced option or an empty breakdown', function () {
    $free = cartWithShippingBreakdown(0);
    $free->freeShippingDiscount = Discount::factory()->create();

    $empty = Cart::factory()->create([
        'currency_id' => $free->currency_id,
    ]);
    $empty->freeShippingDiscount = $free->freeShippingDiscount;
    $empty->shippingBreakdown = new ShippingBreakdown;

    app(ApplyDiscountFreeShipping::class)->handle($free, fn ($cart) => $cart);
    app(ApplyDiscountFreeShipping::class)->handle($empty, fn ($cart) => $cart);

    expect($free->shippingDiscountTotal)->toBeNull()
        ->and($free->shippingSubTotal->value)->toBe(0)
        ->and($empty->shippingDiscountTotal)->toBeNull()
        ->and($empty->shippingSubTotal)->toBeNull();
});

test('returns what the next stage returns', function (bool $hasDiscount) {
    $cart = cartWithShippingBreakdown(1990);
    $cart->freeShippingDiscount = $hasDiscount ? Discount::factory()->create() : null;

    $passed = null;

    $result = app(ApplyDiscountFreeShipping::class)->handle($cart, function ($cart) use (&$passed) {
        $passed = $cart;

        return 'next';
    });

    expect($result)->toBe('next')
        ->and($passed)->toBe($cart);
})->with([
    'with a free shipping discount' => true,
    'without a free shipping discount' => false,
]);
