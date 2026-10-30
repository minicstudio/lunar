<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Lunar\Actions\Orders\GetOrderSplitGates;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Currency::factory()->create([
        'code' => 'GBP',
        'default' => true,
    ]);
});

function splitGateOrder(array $attributes = []): Order
{
    return Order::factory()->create($attributes);
}

function enableSplitOrderSending(): void
{
    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['magister']);
    Config::set('lunar.erp.magister.enabled', true);
    Config::set('lunar.erp.actions.send_order', ['magister']);
}

it('fails each split gate on its own', function (string $gate, array $attributes) {
    $order = splitGateOrder($attributes);

    expect(GetOrderSplitGates::run($order)->failed)->toBe([$gate])
        ->and(GetOrderSplitGates::run($order)->passes())->toBeFalse();
})->with([
    'split' => ['closed', ['status' => 'split']],
    'dispatched' => ['closed', ['status' => 'dispatched']],
    'payment received' => ['paid', ['status' => 'payment-received']],
    'invoice' => ['invoice', ['meta' => ['billing_series' => 'S', 'billing_number' => '10']]],
    'awb' => ['awb', ['meta' => ['awb' => 'AWB-1']]],
    'erp timestamp' => ['erp', ['meta' => ['erp_sent_at' => '2026-10-28T10:00:00+00:00']]],
]);

it('fails when a successful capture exists and ignores an uncaptured intent', function () {
    $captured = splitGateOrder();
    $captured->transactions()->save(Transaction::factory()->make([
        'order_id' => $captured->id,
        'type' => 'capture',
        'success' => true,
    ]));

    $intent = splitGateOrder();
    $intent->transactions()->save(Transaction::factory()->make([
        'order_id' => $intent->id,
        'type' => 'intent',
        'success' => true,
    ]));

    expect(GetOrderSplitGates::run($captured)->failed)->toBe(['paid'])
        ->and(GetOrderSplitGates::run($intent)->failed)->toBe([]);
});

it('allows a split after stock was already taken', function () {
    Config::set('lunar-frontend.orders.stock.decrease', ['awaiting-payment']);
    Config::set('lunar-frontend.orders.stock.increase', ['canceled']);

    activity()->enableLogging();

    $decreased = splitGateOrder(['status' => 'order-received']);
    $decreased->update(['status' => 'awaiting-payment']);

    $restored = splitGateOrder(['status' => 'awaiting-payment']);
    $restored->update(['status' => 'canceled']);

    expect(GetOrderSplitGates::run($decreased->fresh())->failed)->toBe([])
        ->and(GetOrderSplitGates::run($restored->fresh())->failed)->toBe([]);

    activity()->disableLogging();
});

it('lets a payment-offline order with an empty timestamp pass and reports the erp warning', function () {
    enableSplitOrderSending();

    $offline = splitGateOrder(['status' => 'payment-offline', 'meta' => []]);
    $awaiting = splitGateOrder(['status' => 'awaiting-payment', 'meta' => []]);
    $sent = splitGateOrder(['status' => 'payment-offline', 'meta' => ['erp_sent_at' => '2026-10-28T10:00:00+00:00']]);

    $offlineResult = GetOrderSplitGates::run($offline);
    $awaitingResult = GetOrderSplitGates::run($awaiting);

    Config::set('lunar.erp.enabled', false);
    $sendingDisabled = splitGateOrder(['status' => 'payment-offline', 'meta' => []]);

    expect($offlineResult->failed)->toBe([])
        ->and($offlineResult->erpWarning)->toBeTrue()
        ->and($offlineResult->passes())->toBeTrue()
        ->and($awaitingResult->failed)->toBe([])
        ->and($awaitingResult->erpWarning)->toBeFalse()
        ->and(GetOrderSplitGates::run($sent)->failed)->toBe(['erp'])
        ->and(GetOrderSplitGates::run($sent)->erpWarning)->toBeFalse()
        ->and(GetOrderSplitGates::run($sendingDisabled)->failed)->toBe([])
        ->and(GetOrderSplitGates::run($sendingDisabled)->erpWarning)->toBeFalse();
});
