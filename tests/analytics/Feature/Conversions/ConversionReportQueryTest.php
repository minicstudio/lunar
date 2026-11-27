<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Analytics\Conversions\Enums\ConversionEventType;
use Lunar\Analytics\Conversions\Models\ConversionEvent;
use Lunar\Analytics\Conversions\Reports\ConversionReportQuery;
use Lunar\Analytics\Traffic\Reports\TrafficReportQuery;
use Lunar\Models\Cart;
use Lunar\Models\Order;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
});

function conversionReport(string $from = '2026-10-01', string $to = '2026-10-07'): ConversionReportQuery
{
    return new ConversionReportQuery(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
}

function conversionEvents(int $count, ConversionEventType $type, string $at, int $cartId = 9000): void
{
    ConversionEvent::factory()->count($count)->create([
        'type' => $type,
        'cart_id' => $cartId,
        'created_at' => CarbonImmutable::parse($at),
    ]);
}

/**
 * A cart that the shopper filled and took to checkout, as the recorder stores it.
 */
function convertedCart(string $at, int $adds = 1, bool $checkout = true): Cart
{
    $cart = Cart::factory()->create(['created_at' => CarbonImmutable::parse($at)]);

    conversionEvents(1, ConversionEventType::CartStarted, $at, $cart->id);
    conversionEvents($adds, ConversionEventType::AddToCart, $at, $cart->id);

    if ($checkout) {
        conversionEvents(1, ConversionEventType::BeginCheckout, $at, $cart->id);
    }

    return $cart;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function conversionPurchase(string $placedAt, array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'meta' => ['payment_option' => 'cash-on-delivery'],
        'status' => 'payment-received',
        'placed_at' => CarbonImmutable::parse($placedAt),
        'created_at' => CarbonImmutable::parse($placedAt),
    ], $attributes));
}

/**
 * A purchase from a tracked cart, whose cart start and checkout start were
 * recorded at `$trackedAt`, by default before the 1 to 7 October period.
 */
function trackedPurchase(string $placedAt, string $trackedAt = '2026-09-20 10:00'): Order
{
    $cart = Cart::factory()->create(['created_at' => CarbonImmutable::parse($trackedAt)]);

    conversionEvents(1, ConversionEventType::CartStarted, $trackedAt, $cart->id);
    conversionEvents(1, ConversionEventType::BeginCheckout, $trackedAt, $cart->id);

    return conversionPurchase($placedAt, ['cart_id' => $cart->id]);
}

/**
 * A purchase whose cart has no recorded event, for example a cart filled before recording was switched on.
 */
function untrackedPurchase(string $placedAt): Order
{
    return conversionPurchase($placedAt, ['cart_id' => Cart::factory()->create()->id]);
}

test('the default period covers the 30 days that end today', function () {
    conversionEvents(1, ConversionEventType::AddToCart, '2026-09-15 23:59:59');
    conversionEvents(1, ConversionEventType::AddToCart, '2026-09-16 00:00:00');
    conversionEvents(1, ConversionEventType::AddToCart, '2026-10-15 11:00:00');
    conversionPurchase('2026-09-15 23:59:59');
    conversionPurchase('2026-09-16 00:00:00');

    $report = new ConversionReportQuery(CarbonImmutable::today()->subDays(29), CarbonImmutable::today());

    expect($report->from()->toDateTimeString())->toBe('2026-09-16 00:00:00')
        ->and($report->to()->toDateTimeString())->toBe('2026-10-15 23:59:59')
        ->and($report->totals())->toMatchArray(['add_to_cart' => 2, 'purchases' => 1]);
});

test('events and orders outside a custom period are not counted', function () {
    foreach (['2026-09-30 23:59:59', '2026-10-08 00:00:00'] as $outside) {
        conversionEvents(1, ConversionEventType::AddToCart, $outside);
        conversionEvents(1, ConversionEventType::CartStarted, $outside);
        conversionEvents(1, ConversionEventType::BeginCheckout, $outside);
        conversionPurchase($outside);
    }

    conversionEvents(1, ConversionEventType::AddToCart, '2026-10-01 00:00:00');
    conversionEvents(1, ConversionEventType::CartStarted, '2026-10-01 00:00:00');
    conversionEvents(1, ConversionEventType::BeginCheckout, '2026-10-07 23:59:59');
    conversionPurchase('2026-10-07 23:59:59');

    expect(conversionReport()->totals())->toMatchArray([
        'add_to_cart' => 1,
        'carts' => 1,
        'checkouts' => 1,
        'purchases' => 1,
    ]);
});

test('a week of shopping shows its adds to cart, carts, checkouts, purchases and rates', function () {
    conversionEvents(120, ConversionEventType::AddToCart, '2026-10-02 10:00');
    conversionEvents(50, ConversionEventType::CartStarted, '2026-10-02 10:00');
    conversionEvents(20, ConversionEventType::BeginCheckout, '2026-10-03 10:00');

    foreach (range(1, 8) as $i) {
        trackedPurchase('2026-10-04 10:00');
    }

    expect(conversionReport()->totals())->toBe([
        'add_to_cart' => 120,
        'carts' => 50,
        'checkouts' => 20,
        'purchases' => 8,
        'tracked_purchases' => 8,
        'checkout_rate' => 0.4,
        'purchase_rate' => 0.4,
        'cart_purchase_rate' => 0.16,
    ]);
});

test('purchases count orders with and without a source, as the traffic report counts orders', function () {
    conversionPurchase('2026-10-02 10:00', ['meta' => ['traffic_source' => ['visit_id' => 1, 'source' => 'facebook', 'medium' => 'social', 'campaign' => null]]]);
    conversionPurchase('2026-10-02 11:00');
    conversionPurchase('2026-10-02 12:00', ['status' => 'test-order']);

    $traffic = new TrafficReportQuery(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-07'));

    expect(conversionReport()->totals()['purchases'])->toBe(2)
        ->and($traffic->totals()['orders'])->toBe(2);
});

test('a split order counts as one purchase on the original order\'s placement date', function () {
    $original = trackedPurchase('2026-10-02 10:00');
    $newOrders = array_map(fn (): Order => conversionPurchase('2026-10-03 10:00', [
        'meta' => ['split_from_order_id' => $original->id],
    ]), range(1, 2));

    $original->meta = [...$original->meta->getArrayCopy(), 'split_into_order_ids' => array_map(fn (Order $order): int => $order->id, $newOrders)];
    $original->status = 'split';
    $original->save();

    $report = conversionReport();

    expect($report->totals()['purchases'])->toBe(1)
        ->and($report->overTime()['steps']['tracked_purchases'])->toMatchArray([
            '2026-10-02' => 1,
            '2026-10-03' => 0,
        ]);
});

test('purchases from carts without recorded events count, but the purchase rates and the steps over time use only purchases from tracked carts', function () {
    conversionEvents(20, ConversionEventType::BeginCheckout, '2026-10-02 10:00');
    conversionEvents(40, ConversionEventType::CartStarted, '2026-10-02 10:00');

    foreach (range(1, 8) as $i) {
        trackedPurchase('2026-10-04 10:00');
    }

    untrackedPurchase('2026-10-04 11:00');
    conversionPurchase('2026-10-04 12:00');

    $traffic = new TrafficReportQuery(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-07'));

    expect(conversionReport()->totals())->toMatchArray([
        'carts' => 40,
        'checkouts' => 20,
        'purchases' => 10,
        'tracked_purchases' => 8,
        'purchase_rate' => 0.4,
        'cart_purchase_rate' => 0.2,
    ])->and($traffic->totals()['orders'])->toBe(10)
        ->and(array_sum(conversionReport()->overTime()['steps']['tracked_purchases']))->toBe(8);
});

test('a rate whose divisor is zero has no value', function () {
    conversionEvents(5, ConversionEventType::CartStarted, '2026-10-02 10:00');
    trackedPurchase('2026-10-03 10:00');

    expect(conversionReport()->totals())->toMatchArray([
        'checkouts' => 0,
        'checkout_rate' => 0.0,
        'purchase_rate' => null,
        'cart_purchase_rate' => 0.2,
    ])->and(conversionReport('2026-11-01', '2026-11-07')->totals())->toMatchArray([
        'checkout_rate' => null,
        'purchase_rate' => null,
        'cart_purchase_rate' => null,
    ]);
});

test('purchases from tracked carts started before the period give a cart purchase rate of 150%', function () {
    conversionEvents(2, ConversionEventType::CartStarted, '2026-10-02 10:00');

    foreach (range(1, 3) as $i) {
        trackedPurchase('2026-10-04 10:00', trackedAt: '2026-09-28 10:00');
    }

    expect(conversionReport()->totals())->toMatchArray([
        'carts' => 2,
        'purchases' => 3,
        'tracked_purchases' => 3,
        'cart_purchase_rate' => 1.5,
    ]);
});

test('the events of a cart whose order was moved to test-order are left out, while a cart without an order counts', function () {
    $testCart = convertedCart('2026-10-02 10:00', adds: 2);
    conversionPurchase('2026-10-02 11:00', ['cart_id' => $testCart->id, 'status' => 'test-order']);

    convertedCart('2026-10-03 10:00');

    expect(conversionReport()->totals())->toMatchArray([
        'add_to_cart' => 1,
        'carts' => 1,
        'checkouts' => 1,
        'purchases' => 0,
    ]);
});

test('the steps are shown per day for 1 to 7 October, and a day without checkouts shows 0', function () {
    convertedCart('2026-10-02 10:00', adds: 2, checkout: false);
    $purchasedCart = convertedCart('2026-10-03 10:00');
    conversionPurchase('2026-10-03 11:00', ['cart_id' => $purchasedCart->id]);
    untrackedPurchase('2026-10-04 11:00');

    $overTime = conversionReport()->overTime();

    expect($overTime['interval'])->toBe('day')
        ->and(array_keys($overTime['steps']))->toBe(['add_to_cart', 'carts', 'checkouts', 'tracked_purchases'])
        ->and($overTime['steps']['add_to_cart'])->toBe([
            '2026-10-01' => 0,
            '2026-10-02' => 2,
            '2026-10-03' => 1,
            '2026-10-04' => 0,
            '2026-10-05' => 0,
            '2026-10-06' => 0,
            '2026-10-07' => 0,
        ])
        ->and($overTime['steps']['carts'])->toMatchArray(['2026-10-02' => 1, '2026-10-03' => 1])
        ->and($overTime['steps']['checkouts'])->toMatchArray(['2026-10-02' => 0, '2026-10-03' => 1])
        ->and($overTime['steps']['tracked_purchases'])->toMatchArray(['2026-10-02' => 0, '2026-10-03' => 1, '2026-10-04' => 0]);
});

test('the steps are shown for the same 14 weeks as visits from 4 August to 8 November', function () {
    conversionEvents(2, ConversionEventType::AddToCart, '2026-08-04 00:00');
    conversionEvents(1, ConversionEventType::CartStarted, '2026-08-09 23:59');
    conversionEvents(3, ConversionEventType::BeginCheckout, '2026-08-10 00:00');
    trackedPurchase('2026-11-08 12:00');

    $from = CarbonImmutable::parse('2026-08-04');
    $to = CarbonImmutable::parse('2026-11-08');
    $overTime = (new ConversionReportQuery($from, $to))->overTime();
    $visitWeeks = array_keys((new TrafficReportQuery($from, $to))->visitsOverTime()['visits']);

    expect($overTime['interval'])->toBe('week');

    foreach ($overTime['steps'] as $weeks) {
        expect(array_keys($weeks))->toBe($visitWeeks)
            ->and($weeks)->toHaveCount(14);
    }

    expect($overTime['steps']['add_to_cart']['2026-08-04'])->toBe(2)
        ->and($overTime['steps']['carts']['2026-08-04'])->toBe(1)
        ->and($overTime['steps']['checkouts']['2026-08-10'])->toBe(3)
        ->and($overTime['steps']['tracked_purchases']['2026-11-02'])->toBe(1);
});

test('recording switched on mid-period gives events from that day, purchases from the whole period, and rates and steps over time from the tracked purchase', function () {
    untrackedPurchase('2026-10-01 10:00');
    untrackedPurchase('2026-10-02 10:00');

    convertedCart('2026-10-04 10:00');
    $trackedCart = convertedCart('2026-10-06 10:00');

    conversionPurchase('2026-10-06 11:00', ['cart_id' => $trackedCart->id]);

    $report = conversionReport();

    expect($report->totals())->toMatchArray([
        'add_to_cart' => 2,
        'carts' => 2,
        'checkouts' => 2,
        'purchases' => 3,
        'tracked_purchases' => 1,
        'purchase_rate' => 0.5,
        'cart_purchase_rate' => 0.5,
    ])->and(array_sum(array_slice($report->overTime()['steps']['carts'], 0, 3)))->toBe(0)
        ->and($report->overTime()['steps']['tracked_purchases'])->toBe([
            '2026-10-01' => 0,
            '2026-10-02' => 0,
            '2026-10-03' => 0,
            '2026-10-04' => 0,
            '2026-10-05' => 0,
            '2026-10-06' => 1,
            '2026-10-07' => 0,
        ]);
});
