<?php

use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyEngine;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('floors proportional refund clawback', function (int $orderTotal, int $refundAmount, int $earned, int $expected) {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $account = LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => $orderTotal,
        'currency_code' => $currency->code,
    ]);

    app(LoyaltyLedger::class)->earn($account, $earned, LoyaltyEventKey::orderEarn($order->id));

    Transaction::withoutEvents(fn () => Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => 'refund',
        'amount' => $refundAmount,
    ]));

    $refund = $order->refunds()->first();

    app(LoyaltyEngine::class)->adjustForRefund($order, $refund, 1);
    $account->refresh();

    $adjust = $account->transactions()->where('type', LoyaltyTransactionType::Adjust)->first();

    if ($expected === 0) {
        expect($adjust)->toBeNull();
    } else {
        expect($adjust->points)->toBe(-$expected)
            ->and($account->balance)->toBe($earned - $expected);
    }
})->with([
    [10000, 5000, 100, 50],
    [10000, 10000, 100, 100],
    [10000, 1, 100, 0],
    [10000, 33, 100, 0],
    [3, 1, 10, 3],
]);

it('caps clawback to lot remaining_points when points have been partially spent', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $account = LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 10000,
        'currency_code' => $currency->code,
    ]);

    // Earn 100 points, then spend 80 — only 20 remain in the lot.
    $ledger = app(LoyaltyLedger::class);
    $ledger->earn($account, 100, LoyaltyEventKey::orderEarn($order->id));
    $ledger->spend($account, 80, 'spend:other-order');
    $account->refresh();

    Transaction::withoutEvents(fn () => Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => 'refund',
        'amount' => 5000, // 50% refund → would normally claw back 50 points
    ]));

    $refund = $order->refunds()->first();
    app(LoyaltyEngine::class)->adjustForRefund($order, $refund, 1);
    $account->refresh();

    // Clawback is capped to 20 (remaining in lot), not 50.
    expect($account->balance)->toBeGreaterThanOrEqual(0)
        ->and($account->balance)->toBe(0);
});

it('does not claw back when earn was already reversed by cancellation', function () {
    $currency = Currency::factory()->create(['code' => 'GBP', 'default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $account = LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 10000,
        'currency_code' => $currency->code,
    ]);

    $ledger = app(LoyaltyLedger::class);
    $ledger->earn($account, 100, LoyaltyEventKey::orderEarn($order->id));
    // Simulate the earn having been reversed (lot zeroed).
    $earnLot = $account->earnTransactions()->first();
    $ledger->expire($earnLot, 'expire:'.$earnLot->id);
    $account->refresh();

    Transaction::withoutEvents(fn () => Transaction::factory()->create([
        'order_id' => $order->id,
        'type' => 'refund',
        'amount' => 5000,
    ]));

    $refund = $order->refunds()->first();
    app(LoyaltyEngine::class)->adjustForRefund($order, $refund, 1);
    $account->refresh();

    // Nothing to claw back — balance stays at 0, no negative value.
    expect($account->balance)->toBe(0)
        ->and($account->transactions()->where('type', LoyaltyTransactionType::Adjust)->exists())->toBeFalse();
});
