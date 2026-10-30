<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Lunar\Actions\Orders\GetOrderProductLineEditGates;
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

function gateOrder(array $attributes = []): Order
{
    return Order::factory()->create($attributes);
}

it('rejects a split order and does not reject an awaiting-payment order for that status', function () {
    $split = gateOrder(['status' => 'split']);
    $awaiting = gateOrder(['status' => 'awaiting-payment']);

    expect(GetOrderProductLineEditGates::run($split))->toBe(['split'])
        ->and(GetOrderProductLineEditGates::run($awaiting))->toBe([]);
});

it('fails each gate on its own', function () {
    $paid = gateOrder(['status' => 'payment-received']);
    $sent = gateOrder(['meta' => ['erp_sent_at' => now()->toIso8601String()]]);
    $invoiced = gateOrder(['meta' => ['billing_series' => 'S', 'billing_number' => '10']]);
    $labelled = gateOrder(['meta' => ['awb' => 'AWB-1']]);

    expect(GetOrderProductLineEditGates::run($paid))->toBe(['paid'])
        ->and(GetOrderProductLineEditGates::run($sent))->toBe(['erp'])
        ->and(GetOrderProductLineEditGates::run($invoiced))->toBe(['invoice'])
        ->and(GetOrderProductLineEditGates::run($labelled))->toBe(['awb']);
});

it('treats a refunded capture as paid and an uncaptured intent as unpaid', function () {
    $captured = gateOrder();
    $captured->transactions()->save(Transaction::factory()->make([
        'type' => 'capture',
        'success' => true,
    ]));
    $captured->transactions()->save(Transaction::factory()->make([
        'type' => 'refund',
        'success' => true,
    ]));

    $intent = gateOrder();
    $intent->transactions()->save(Transaction::factory()->make([
        'type' => 'intent',
        'success' => true,
    ]));

    $unpaid = gateOrder();

    expect(GetOrderProductLineEditGates::run($captured))->toBe(['paid'])
        ->and(GetOrderProductLineEditGates::run($intent))->toBe([])
        ->and(GetOrderProductLineEditGates::run($unpaid))->toBe([]);
});

it('does not fail the invoice gate when only one identifier is filled', function () {
    $seriesOnly = gateOrder(['meta' => ['billing_series' => 'S']]);
    $numberOnly = gateOrder(['meta' => ['billing_number' => '10']]);

    expect(GetOrderProductLineEditGates::run($seriesOnly))->toBe([])
        ->and(GetOrderProductLineEditGates::run($numberOnly))->toBe([]);
});

it('does not fail the erp gate for an awaiting-payment order with an empty timestamp', function () {
    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['magister']);
    Config::set('lunar.erp.magister.enabled', true);
    Config::set('lunar.erp.actions.send_order', ['magister']);

    $awaiting = gateOrder(['status' => 'awaiting-payment', 'meta' => []]);
    $historical = gateOrder(['status' => 'payment-offline', 'meta' => []]);
    $awaitingGates = GetOrderProductLineEditGates::run($awaiting);
    $historicalGates = GetOrderProductLineEditGates::run($historical);

    Config::set('lunar.erp.enabled', false);
    $sendingDisabled = gateOrder(['status' => 'payment-offline', 'meta' => []]);

    expect($awaitingGates)->toBe([])
        ->and($historicalGates)->toBe(['erp'])
        ->and(GetOrderProductLineEditGates::run($sendingDisabled))->toBe([]);
});
