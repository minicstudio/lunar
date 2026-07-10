<?php

use Lunar\DataTypes\Price as DataTypesPrice;
use Lunar\Loyalty\Pipelines\Cart\AdjustCartTotalsForLoyalty;
use Lunar\Models\Cart;
use Lunar\Models\Currency;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('adjust cart totals matches checkout summary line items', function () {
    $currency = Currency::factory()->create(['decimal_places' => 2]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'meta' => [
            'loyalty_points_to_redeem' => 100,
            'loyalty_discount_minor' => 100,
        ],
    ]);

    $cart->subTotalDiscountedWithoutCouponIncTax = new DataTypesPrice(1600, $currency, 1);
    $cart->shippingTotal = new DataTypesPrice(1900, $currency, 1);
    $cart->couponTotalIncTax = new DataTypesPrice(160, $currency, 1);
    $cart->total = new DataTypesPrice(3340, $currency, 1);
    $cart->setRelation('lines', collect());

    $result = app(AdjustCartTotalsForLoyalty::class)->handle($cart, fn ($cart) => $cart);

    expect($result->loyaltyTotalIncTax?->value)->toBe(100)
        ->and($result->total->value)->toBe(3240);
});
