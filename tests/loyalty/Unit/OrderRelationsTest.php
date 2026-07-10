<?php

use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('resolves loyalty earn and spend transactions on order', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $account = LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'currency_code' => $currency->code,
    ]);

    $ledger = app(LoyaltyLedger::class);
    $ledger->earn($account, 200, LoyaltyEventKey::orderEarn($order->id), ['reference' => $order]);
    $ledger->spend($account, 80, LoyaltyEventKey::orderSpend($order->id), ['reference' => $order]);

    $order->refresh();

    expect($order->loyaltyEarnTransaction?->points)->toBe(200)
        ->and($order->loyaltySpendTransaction?->points)->toBe(80);
});

it('returns null relations when no loyalty transactions exist', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $order = Order::factory()->create(['currency_code' => $currency->code]);

    expect($order->loyaltyEarnTransaction)->toBeNull()
        ->and($order->loyaltySpendTransaction)->toBeNull();
});

it('does not error when relations are accessed on an unsaved order', function () {
    $order = new Order;

    expect($order->loyaltyEarnTransaction)->toBeNull()
        ->and($order->loyaltySpendTransaction)->toBeNull();
});
