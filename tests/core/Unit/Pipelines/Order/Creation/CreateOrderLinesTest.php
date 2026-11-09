<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Pipelines\Order\Creation\CreateOrderLines;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);

uses(RefreshDatabase::class);

test('can run pipeline', function () {
    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    $purchasable = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 100,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => 1,
    ]);

    $order = Order::factory()->create([
        'cart_id' => $cart->id,
    ]);

    $cart->recalculate();

    app(CreateOrderLines::class)->handle($order, function ($order) {
        return $order;
    });

    expect($order->lines)->toHaveCount($cart->lines->count());
});

test('records a preorder variant and leaves that record in place', function () {
    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    $preorder = ProductVariant::factory()->create([
        'purchasable' => 'preorder',
    ]);
    $normal = ProductVariant::factory()->create();

    foreach ([$preorder, $normal] as $variant) {
        Price::factory()->create([
            'price' => 100,
            'min_quantity' => 1,
            'currency_id' => $currency->id,
            'priceable_type' => $variant->getMorphClass(),
            'priceable_id' => $variant->id,
        ]);

        $cart->lines()->create([
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'quantity' => 1,
        ]);
    }

    $order = Order::factory()->create([
        'cart_id' => $cart->id,
    ]);

    $order = app(CreateOrderLines::class)->handle($order, fn ($order) => $order);

    $preorderLine = $order->lines->firstWhere('purchasable_id', $preorder->id);
    $normalLine = $order->lines->firstWhere('purchasable_id', $normal->id);

    expect($preorderLine->meta['preorder'])->toBeTrue()
        ->and($normalLine->meta['preorder'] ?? null)->not->toBeTrue();

    $order = app(CreateOrderLines::class)->handle($order, fn ($order) => $order);

    expect($order->lines)->toHaveCount(2);

    $preorder->update([
        'purchasable' => 'always',
    ]);

    $order = app(CreateOrderLines::class)->handle($order->refresh(), fn ($order) => $order);
    $recorded = $order->lines->firstWhere('purchasable_id', $preorder->id);

    expect($order->lines)->toHaveCount(2)
        ->and($recorded->meta['preorder'])->toBeTrue();
});
