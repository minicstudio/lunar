<?php

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Lunar\DataTypes\Price as DataTypesPrice;
use Lunar\DataTypes\ShippingOption;
use Lunar\Facades\ShippingManifest;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Models\LoyaltyTransaction;
use Lunar\Loyalty\Services\LoyaltyRedeemer;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;

test('order line without_coupon prices exclude loyalty redemption', function () {
    config([
        'lunar.pricing.stored_inclusive_of_tax' => false,
    ]);

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

    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $customer->customerGroups()->attach($customerGroup);

    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    $taxClass = TaxClass::factory()->create();

    TaxRateAmount::factory()->create([
        'percentage' => 20,
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    $purchasableA = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
        'unit_quantity' => 1,
    ]);

    $purchasableB = ProductVariant::factory()->create([
        'tax_class_id' => $taxClass->id,
        'unit_quantity' => 1,
    ]);

    Price::factory()->create([
        'price' => 2500,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasableA->getMorphClass(),
        'priceable_id' => $purchasableA->id,
    ]);

    Price::factory()->create([
        'price' => 5000,
        'min_quantity' => 1,
        'currency_id' => $currency->id,
        'priceable_type' => $purchasableB->getMorphClass(),
        'priceable_id' => $purchasableB->id,
    ]);

    $cart = Cart::create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasableA->getMorphClass(),
        'purchasable_id' => $purchasableA->id,
        'quantity' => 8,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $purchasableB->getMorphClass(),
        'purchasable_id' => $purchasableB->id,
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
        price: new DataTypesPrice(1500, $cart->currency, 1),
        taxClass: $taxClass
    );

    ShippingManifest::addOption($shippingOption);
    $cart->setShippingOption($shippingOption);

    $account = LoyaltyAccount::factory()->create([
        'customer_id' => $customer->id,
        'balance' => 10000,
    ]);

    LoyaltyTransaction::factory()->create([
        'loyalty_account_id' => $account->id,
        'points' => 10000,
        'remaining_points' => 10000,
        'event_key' => LoyaltyEventKey::adjust(),
        'expires_at' => now()->addYear(),
    ]);

    $cart = $cart->calculate();

    $cartLineA = $cart->lines->firstWhere('purchasable_id', $purchasableA->id);
    $cartLineB = $cart->lines->firstWhere('purchasable_id', $purchasableB->id);

    expect($cartLineA->unitPriceWithoutCouponIncTax->value)->toBe(3000)
        ->and($cartLineA->subTotalDiscountedWithoutCouponIncTax->value)->toBe(24000)
        ->and($cartLineB->unitPriceWithoutCouponIncTax->value)->toBe(6000)
        ->and($cartLineB->subTotalDiscountedWithoutCouponIncTax->value)->toBe(12000);

    $cart = app(LoyaltyRedeemer::class)->applyToCart($cart, 2500);

    expect($cart->lines->firstWhere('purchasable_id', $purchasableA->id)->unitPriceWithoutCouponIncTax->value)->toBe(3000)
        ->and($cart->lines->firstWhere('purchasable_id', $purchasableB->id)->unitPriceWithoutCouponIncTax->value)->toBe(6000);

    $order = $cart->createOrder()->refresh()->load('lines');

    $orderLineA = $order->productLines->firstWhere('purchasable_id', $purchasableA->id);
    $orderLineB = $order->productLines->firstWhere('purchasable_id', $purchasableB->id);

    expect((int) data_get($order->meta, 'loyalty_discount_minor'))->toBe(2500)
        ->and($orderLineA->discount_total->value)->toBeGreaterThan(0)
        ->and($orderLineB->discount_total->value)->toBeGreaterThan(0)
        ->and($orderLineA->unit_price_without_coupon->value)->toBe(2500)
        ->and($orderLineA->unit_price_without_coupon_inc_tax->value)->toBe(3000)
        ->and($orderLineA->price_without_coupon->value)->toBe(20000)
        ->and($orderLineA->price_without_coupon_inc_tax->value)->toBe(24000)
        ->and($orderLineB->unit_price_without_coupon->value)->toBe(5000)
        ->and($orderLineB->unit_price_without_coupon_inc_tax->value)->toBe(6000)
        ->and($orderLineB->price_without_coupon->value)->toBe(10000)
        ->and($orderLineB->price_without_coupon_inc_tax->value)->toBe(12000)
        ->and($order->sub_total_discounted_without_coupon_inc_tax->value)->toBe(36000);
});
