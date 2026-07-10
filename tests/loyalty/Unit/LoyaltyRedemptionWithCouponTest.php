<?php

use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Facades\Discounts;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Models\LoyaltyTransaction;
use Lunar\Loyalty\Services\LoyaltyRedeemer;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('stacks coupon and loyalty redemption on cart calculate', function () {
    $currency = Currency::factory()->create(['default' => true, 'decimal_places' => 2]);
    $channel = Channel::factory()->create(['default' => true]);
    $customerGroup = CustomerGroup::factory()->create(['default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'LOYALTY10',
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
            'min_prices' => [$currency->code => 0],
        ],
    ]);

    $discount->channels()->sync([$channel->id => ['enabled' => true, 'starts_at' => now()]]);
    $discount->customerGroups()->sync([$customerGroup->id => ['enabled' => true, 'visible' => true, 'starts_at' => now()]]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
        'coupon_code' => 'LOYALTY10',
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 2,
    ]);

    $account = LoyaltyAccount::factory()->create([
        'customer_id' => $customer->id,
        'balance' => 5000,
    ]);

    LoyaltyTransaction::factory()->create([
        'loyalty_account_id' => $account->id,
        'points' => 5000,
        'remaining_points' => 5000,
        'event_key' => LoyaltyEventKey::adjust(),
        'expires_at' => now()->addYear(),
    ]);

    Discounts::resetDiscounts();
    $cart = $cart->refresh()->load('lines')->calculate();

    expect($cart->couponTotalIncTax?->value)->toBeGreaterThan(0);

    $cart = app(LoyaltyRedeemer::class)->applyToCart($cart, 500);

    expect($cart->couponTotalIncTax?->value)->toBeGreaterThan(0)
        ->and($cart->loyaltyTotalIncTax?->value)->toBeGreaterThan(0)
        ->and($cart->total?->value)->toBeGreaterThanOrEqual(0);

    $meta = $cart->meta?->toArray() ?? (array) $cart->meta;

    expect($meta['loyalty_points_to_redeem'] ?? 0)->toBe(500);
});
