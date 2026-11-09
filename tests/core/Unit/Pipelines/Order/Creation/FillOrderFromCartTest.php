<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Pipelines\Order\Creation\FillOrderFromCart;
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

    /** @var Order $order */
    $order = new Order([
        'cart_id' => $cart->id,
    ]);

    $cart->calculate();

    app(FillOrderFromCart::class)->handle($order, function ($order) {
        return $order;
    });

    expect($order->reference)->not->toBeNull();
    expect($order->user_id)->toEqual($cart->user_id);
    expect($order->channel_id)->toEqual($cart->channel_id);
    expect($order->sub_total->value)->toEqual($cart->subTotal->value);
    expect($order->discount_otal?->value)->toEqual($cart->discountTotal?->value);
    expect($order->tax_total->value)->toEqual($cart->taxTotal->value);
    expect($order->total->value)->toEqual($cart->total->value);
});

/**
 * A calculable cart with one priced line and the given meta.
 *
 * @param  array<string, mixed>  $meta
 */
function fillableCartWithMeta(array $meta): Cart
{
    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'meta' => $meta,
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

    return $cart;
}

test('the order keeps the cart traffic source, also when the draft is rebuilt', function () {
    $trafficSource = [
        'visit_id' => 42,
        'source' => 'facebook',
        'medium' => 'social',
        'campaign' => 'autumn',
    ];

    $cart = fillableCartWithMeta([
        'shippingType' => 'courier',
        'traffic_source' => $trafficSource,
    ]);

    /** @var Order $order */
    $order = new Order(['cart_id' => $cart->id]);

    app(FillOrderFromCart::class)->handle($order, fn ($order) => $order);

    expect($order->fresh()->meta['traffic_source'])->toBe($trafficSource)
        ->and($order->fresh()->meta['shippingType'])->toBe('courier');

    $draft = $order->fresh();

    app(FillOrderFromCart::class)->handle($draft, fn ($order) => $order);

    expect($draft->fresh()->meta['traffic_source'])->toBe($trafficSource);
});

test('an order from a cart without a traffic source has none', function () {
    $cart = fillableCartWithMeta(['shippingType' => 'courier']);

    /** @var Order $order */
    $order = new Order(['cart_id' => $cart->id]);

    app(FillOrderFromCart::class)->handle($order, fn ($order) => $order);

    expect($order->fresh()->meta->getArrayCopy())->not->toHaveKey('traffic_source');
});
