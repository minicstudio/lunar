<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Analytics\Reports\CountedOrders;
use Lunar\Analytics\Reports\ReportPeriod;
use Lunar\Models\Cart;
use Lunar\Models\Order;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
});

function countedOrdersFor(string $from, string $to): CountedOrders
{
    return new CountedOrders(new ReportPeriod(CarbonImmutable::parse($from), CarbonImmutable::parse($to)));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function countedOrder(?Cart $cart, string $placedAt, array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'cart_id' => $cart?->id,
        'meta' => ['payment_option' => 'cash-on-delivery'],
        'status' => 'payment-received',
        'placed_at' => CarbonImmutable::parse($placedAt),
        'created_at' => CarbonImmutable::parse($placedAt),
    ], $attributes));
}

/**
 * Split the order as `SplitOrder` does: new orders with `split_from_order_id`
 * placed at the split, and the original marked `split`.
 *
 * @param  list<string>  $statuses  One new order per status.
 */
function splitCountedOrder(Order $original, string $at, array $statuses): void
{
    $newOrders = array_map(fn (string $status): Order => countedOrder(
        Cart::factory()->create(['created_at' => CarbonImmutable::parse($at)]),
        $at,
        ['status' => $status, 'meta' => ['split_from_order_id' => $original->id]],
    ), $statuses);

    $original->meta = [
        ...$original->meta->getArrayCopy(),
        'split_into_order_ids' => array_map(fn (Order $order): int => $order->id, $newOrders),
    ];
    $original->status = 'split';
    $original->save();
}

test('a split order counts once, as its original on its own placement date', function () {
    $inOctober = countedOrder(Cart::factory()->create(), '2026-10-02 10:00');
    splitCountedOrder($inOctober, '2026-10-03 10:00', ['payment-received', 'awaiting-payment']);

    $inSeptember = countedOrder(Cart::factory()->create(), '2026-09-30 10:00');
    splitCountedOrder($inSeptember, '2026-10-02 10:00', ['payment-received', 'payment-received']);

    expect(countedOrdersFor('2026-10-01', '2026-10-07')->placedQuery()->pluck('id')->all())->toBe([$inOctober->id])
        ->and(countedOrdersFor('2026-09-01', '2026-09-30')->placedQuery()->pluck('id')->all())->toBe([$inSeptember->id]);
});

test('an order placed outside the period is not counted', function () {
    countedOrder(null, '2026-09-30 23:59:59');
    countedOrder(null, '2026-10-08 00:00:00');
    $inside = countedOrder(null, '2026-10-07 23:59:59');

    expect(countedOrdersFor('2026-10-01', '2026-10-07')->placedQuery()->pluck('id')->all())->toBe([$inside->id]);
});

test('the cart of a test order is excluded unless it also has an order that counts', function () {
    $testCart = Cart::factory()->create();
    countedOrder($testCart, '2026-10-02 10:00', ['status' => 'test-order']);

    $keptCart = Cart::factory()->create();
    countedOrder($keptCart, '2026-10-02 10:00', ['status' => 'test-order']);
    countedOrder($keptCart, '2026-10-03 10:00');

    $orders = countedOrdersFor('2026-10-01', '2026-10-07');

    expect($orders->excludedCartIds())->toBe([$testCart->id])
        ->and($orders->placedQuery()->count())->toBe(1);
});
