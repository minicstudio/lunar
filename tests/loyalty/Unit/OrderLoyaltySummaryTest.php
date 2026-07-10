<?php

use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Loyalty\Support\OrderLoyaltySummary;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('reads loyalty redemption from order meta', function () {
    $currency = Currency::factory()->create(['code' => 'RON', 'default' => true]);
    $order = Order::factory()->create([
        'currency_code' => $currency->code,
        'meta' => [
            'loyalty_points_to_redeem' => 200,
            'loyalty_discount_minor' => 200,
        ],
    ]);

    expect(OrderLoyaltySummary::fromOrder($order))->toBe([
        'points' => 200,
        'discount_minor' => 200,
    ]);
});

it('falls back to spend transaction when meta is missing', function () {
    $currency = Currency::factory()->create(['code' => 'RON', 'default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $account = LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'currency_code' => $currency->code,
        'meta' => [],
    ]);

    app(LoyaltyLedger::class)->earn($account, 500, 'earn:1');
    app(LoyaltyLedger::class)->spend($account, 200, LoyaltyEventKey::orderSpend($order->id), ['reference' => $order]);

    expect(OrderLoyaltySummary::fromOrder($order->refresh()))->toBe([
        'points' => 200,
        'discount_minor' => 200,
    ]);
});

it('returns null when no loyalty redemption exists', function () {
    $currency = Currency::factory()->create(['code' => 'RON', 'default' => true]);
    $order = Order::factory()->create([
        'currency_code' => $currency->code,
        'meta' => [],
    ]);

    expect(OrderLoyaltySummary::fromOrder($order))->toBeNull();
});
