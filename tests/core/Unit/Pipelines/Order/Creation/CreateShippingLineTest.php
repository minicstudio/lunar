<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Price as PriceModel;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Pipelines\Order\Creation\CreateShippingLine;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);

uses(RefreshDatabase::class);

test('can run pipeline', function () {
    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    CartAddress::factory()->create([
        'type' => 'billing',
        'cart_id' => $cart->id,
    ]);

    ShippingManifest::addOption(
        new ShippingOption(
            name: 'Basic Delivery',
            description: 'Basic Delivery',
            identifier: 'BASDEL',
            price: new Price(500, $cart->currency, 1),
            taxClass: TaxClass::factory()->create()
        )
    );

    CartAddress::factory()->create([
        'type' => 'shipping',
        'shipping_option' => 'BASDEL',
        'cart_id' => $cart->id,
    ]);

    $order = Order::factory()->create([
        'cart_id' => $cart->id,
    ]);

    $order = app(CreateShippingLine::class)->handle($order, function ($order) {
        return $order;
    });

    expect($order->shippingLines)->toHaveCount(1);

    $shippingLine = $order->shippingLines->first();

    expect($shippingLine->identifier)->toEqual('BASDEL');
    expect($shippingLine->purchasable_type)->toBeNull();
    expect($shippingLine->purchasable_id)->toBeNull();
});

test('can update shipping line if exists', function () {
    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    CartAddress::factory()->create([
        'type' => 'billing',
        'cart_id' => $cart->id,
    ]);

    ShippingManifest::addOption(
        new ShippingOption(
            name: 'Basic Delivery',
            description: 'Basic Delivery',
            identifier: 'BASDEL',
            price: new Price(500, $cart->currency, 1),
            taxClass: TaxClass::factory()->create()
        )
    );

    CartAddress::factory()->create([
        'type' => 'shipping',
        'shipping_option' => 'BASDEL',
        'cart_id' => $cart->id,
    ]);

    $order = Order::factory()->create([
        'cart_id' => $cart->id,
    ]);

    OrderLine::factory()->create([
        'identifier' => 'BASDEL',
        'purchasable_type' => ShippingOption::class,
        'type' => 'shipping',
        'order_id' => $order->id,
    ]);

    $order = app(CreateShippingLine::class)->handle($order->refresh(), function ($order) {
        return $order;
    });

    expect($order->shippingLines)->toHaveCount(1);

    // The legacy fake morph is matched by type + identifier and de-morphed in place.
    $shippingLine = $order->shippingLines->first();

    expect($shippingLine->identifier)->toEqual('BASDEL');
    expect($shippingLine->purchasable_type)->toBeNull();
    expect($shippingLine->purchasable_id)->toBeNull();
});

test('stores the pending shipping marker and a zero shipping total', function () {
    $currency = Currency::factory()->create();

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
    ]);

    CartAddress::factory()->create([
        'type' => 'billing',
        'cart_id' => $cart->id,
    ]);

    ShippingManifest::addOption(
        new ShippingOption(
            name: 'Basic Delivery',
            description: 'Basic Delivery',
            identifier: 'BASDEL',
            price: new Price(0, $cart->currency, 1),
            taxClass: TaxClass::factory()->create(),
            meta: [
                'shipping_zone' => 'UK',
                'preorder_shipping_pending' => true,
            ],
        )
    );

    CartAddress::factory()->create([
        'type' => 'shipping',
        'shipping_option' => 'BASDEL',
        'cart_id' => $cart->id,
    ]);

    $order = Order::factory()->create([
        'cart_id' => $cart->id,
    ]);

    $order = app(CreateShippingLine::class)->handle($order, function ($order) {
        return $order;
    });

    $shippingLine = $order->shippingLines->first();

    expect($shippingLine)->not->toBeNull()
        ->and($shippingLine->meta['preorder_shipping_pending'])->toBeTrue()
        ->and($shippingLine->total->value)->toEqual(0);
});

/**
 * An order whose cart has one 10.00 line and a 19.90 courier option.
 */
function shippingLineOrder(bool $freeShipping): Order
{
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'decimal_places' => 2,
        'default' => true,
    ]);

    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
    ]);

    $variant = ProductVariant::factory()->create();

    PriceModel::factory()->create([
        'price' => 1000,
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

    ShippingManifest::addOption(
        new ShippingOption(
            name: 'Courier',
            description: 'Courier',
            identifier: 'COURIER',
            price: new Price(1990, $cart->currency, 1),
            taxClass: TaxClass::factory()->create()
        )
    );

    CartAddress::factory()->create([
        'type' => 'shipping',
        'shipping_option' => 'COURIER',
        'cart_id' => $cart->id,
    ]);

    if ($freeShipping) {
        $discount = Discount::factory()->create([
            'type' => AdvancedAmountOff::class,
            'coupon' => null,
            'starts_at' => now()->subMinute(),
            'data' => [
                'fixed_value' => false,
                'percentage' => null,
                'free_shipping' => true,
            ],
        ]);

        $discount->channels()->sync([$channel->id => ['enabled' => true, 'starts_at' => now()->subMinute()]]);
        $discount->customerGroups()->sync([$customerGroup->id => ['enabled' => true, 'starts_at' => now()->subMinute()]]);
    }

    return Order::factory()->create([
        'cart_id' => $cart->id,
        'channel_id' => $channel->id,
    ]);
}

test('stores the waived shipping on the shipping line', function () {
    $order = app(CreateShippingLine::class)->handle(shippingLineOrder(freeShipping: true), fn ($order) => $order);

    $shippingLine = $order->shippingLines->first();

    expect($shippingLine->unit_price->value)->toBe(1990)
        ->and($shippingLine->discount_total->value)->toBe(1990)
        ->and($shippingLine->sub_total->value)->toBe(0)
        ->and($shippingLine->tax_total->value)->toBe(0)
        ->and($shippingLine->total->value)->toBe(0)
        ->and($order->shipping_discount_total->value)->toBe(1990);
});

test('stores no shipping discount without a free shipping discount', function () {
    $order = app(CreateShippingLine::class)->handle(shippingLineOrder(freeShipping: false), fn ($order) => $order);

    $shippingLine = $order->shippingLines->first();

    expect($shippingLine->unit_price->value)->toBe(1990)
        ->and($shippingLine->discount_total->value)->toBe(0)
        ->and($shippingLine->sub_total->value)->toBe(1990)
        ->and($order->shipping_discount_total->value)->toBe(0);
});
