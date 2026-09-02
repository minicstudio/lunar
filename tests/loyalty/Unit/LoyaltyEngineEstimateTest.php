<?php

use Lunar\DataTypes\Price;
use Lunar\Loyalty\Facades\Loyalty;
use Lunar\Loyalty\Services\LoyaltyEngine;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Order;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('estimates order points using the same math as earn', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $order = Order::factory()->create([
        'total' => 10000,
        'currency_code' => $currency->code,
    ]);

    expect(Loyalty::estimateOrderPoints($order))->toBe(100)
        ->and(app(LoyaltyEngine::class)->estimateOrderPoints($order))->toBe(100);
});

it('estimates cart points from cart total', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $cart = Cart::factory()->create(['currency_id' => $currency->id]);
    $cart->total = new Price(5000, $currency, 1);

    expect(Loyalty::estimateCartPoints($cart))->toBe(50);
});

it('returns zero when loyalty is disabled', function () {
    config(['lunar.loyalty.enabled' => false]);

    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $order = Order::factory()->create([
        'total' => 10000,
        'currency_code' => $currency->code,
    ]);

    expect(Loyalty::estimateOrderPoints($order))->toBe(0);
});
