<?php

use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('earns points when order status becomes completed', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 10000,
        'currency_code' => $currency->code,
        'status' => 'payment-received',
    ]);

    $order->update(['status' => 'completed']);

    $account = LoyaltyAccount::where('customer_id', $customer->id)->first();

    expect($account->transactions()->where('type', LoyaltyTransactionType::Earn)->count())->toBe(1)
        ->and($account->balance)->toBe(100);
});

it('earns points when a reloaded order record is marked completed in admin', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 10000,
        'currency_code' => $currency->code,
        'status' => 'confirmed',
    ]);

    $record = Order::query()->findOrFail($order->id);
    $record->update(['status' => 'completed']);

    $account = LoyaltyAccount::where('customer_id', $customer->id)->first();

    expect($account->transactions()->where('type', LoyaltyTransactionType::Earn)->count())->toBe(1)
        ->and($account->balance)->toBe(100);
});

it('reverses spend when order is canceled', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $account = LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'currency_code' => $currency->code,
    ]);

    app(LoyaltyLedger::class)->earn($account, 1000, 'earn:1');
    app(LoyaltyLedger::class)->spend($account, 400, LoyaltyEventKey::orderSpend($order->id), ['reference' => $order]);

    $order->update(['status' => 'canceled']);
    $account->refresh();

    expect($account->balance)->toBe(1000)
        ->and($account->lifetime_spent)->toBe(0)
        ->and($account->transactions()->where('type', LoyaltyTransactionType::Adjust)->exists())->toBeTrue();
});

it('reverses earned points when a completed order is later canceled', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $account = LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 10000,
        'currency_code' => $currency->code,
        'status' => 'payment-received',
    ]);

    $order->update(['status' => 'completed']);
    $account->refresh();

    $earnedPoints = $account->balance;
    expect($earnedPoints)->toBeGreaterThan(0);

    $order->update(['status' => 'canceled']);
    $account->refresh();

    expect($account->balance)->toBe(0)
        ->and($account->lifetime_earned)->toBe(0)
        ->and($account->transactions()->where('type', LoyaltyTransactionType::Adjust)->count())->toBe(1);

    $reversal = $account->transactions()->where('type', LoyaltyTransactionType::Adjust)->first();
    expect($reversal->points)->toBe(-$earnedPoints);
});
