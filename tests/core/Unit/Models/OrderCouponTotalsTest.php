<?php

uses(\Lunar\Tests\Core\TestCase::class);

use Lunar\DataTypes\Price as DataTypesPrice;
use Lunar\DataTypes\ShippingOption;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('order exposes net subtotal, shipping and coupon totals alongside the gross ones', function () {
    $currency = Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);

    $channel = Channel::factory()->create([
        'default' => true,
    ]);

    $customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    $taxClass = TaxClass::factory()->create();

    TaxRateAmount::factory()->create([
        'percentage' => 20,
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'name' => 'Test Coupon',
        'coupon' => 'save10',
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);

    $discount->channels()->sync([
        $channel->id => [
            'enabled' => true,
            'starts_at' => now(),
        ],
    ]);

    $discount->customerGroups()->sync([
        $customerGroup->id => [
            'enabled' => true,
            'visible' => true,
            'starts_at' => now(),
        ],
    ]);

    $purchasable = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
        'unit_quantity' => 1,
    ]);

    Price::factory()->create([
        'price' => 1000,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasable->getMorphClass(),
        'priceable_id' => $purchasable->id,
    ]);

    $cart = Cart::create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
        'coupon_code' => 'save10',
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasable->getMorphClass(),
        'purchasable_id' => $purchasable->id,
        'quantity' => 2,
    ]);

    CartAddress::factory()->create([
        'type' => 'billing',
        'cart_id' => $cart->id,
    ]);

    CartAddress::factory()->create([
        'type' => 'shipping',
        'cart_id' => $cart->id,
    ]);

    $shippingOption = new ShippingOption(
        name: 'Basic Delivery',
        description: 'Basic Delivery',
        identifier: 'BASDEL',
        price: new DataTypesPrice(500, $cart->currency, 1),
        taxClass: $taxClass
    );

    ShippingManifest::addOption($shippingOption);

    $cart->setShippingOption($shippingOption);

    $cart->calculate();

    // Sanity check the cart-level figures the order accessors should mirror.
    expect($cart->subTotalDiscountedWithoutCoupon->value)->toBe(2000);
    expect($cart->shippingSubTotal->value)->toBe(500);
    expect($cart->couponTotal->value)->toBe(200);

    $order = $cart->createOrder();

    expect($order->sub_total_discounted_without_coupon->value)->toBe(2000);
    expect($order->sub_total_discounted_without_coupon_inc_tax->value)->toBe(2400);

    expect($order->shipping_sub_total->value)->toBe(500);

    // Net coupon amount matches the cart-level coupon total (both are net/pre-tax).
    expect($order->coupon_total_without_tax->value)->toBe(200);
    // Gross coupon amount includes the 20% tax rate on top of the net amount.
    expect($order->coupon_total->value)->toBe(240);

    $productLine = $order->productLines->first();

    expect($productLine->price_without_coupon->value)->toBe(2000);
    expect($productLine->price_without_coupon_inc_tax->value)->toBe(2400);
});
