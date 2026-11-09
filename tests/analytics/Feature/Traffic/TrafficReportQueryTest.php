<?php

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
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

function octoberReport(): TrafficReportQuery
{
    return new TrafficReportQuery(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-07'));
}

function reportVisits(int $count, string $source, ?string $medium, string $at, string $landingPath = '/'): void
{
    TrafficVisit::factory()->count($count)->create([
        'source' => $source,
        'medium' => $medium,
        'landing_path' => $landingPath,
        'created_at' => CarbonImmutable::parse($at),
    ]);
}

/**
 * @param  ?array{source: string, medium: ?string}  $source
 */
function reportCart(?array $source, string $at): Cart
{
    $meta = $source === null ? ['shippingType' => 'courier'] : ['traffic_source' => ['visit_id' => 1, ...$source, 'campaign' => null]];

    return Cart::factory()->create(['meta' => $meta, 'created_at' => CarbonImmutable::parse($at)]);
}

/**
 * @param  ?array{source: string, medium: ?string}  $source
 * @param  array<string, mixed>  $attributes
 */
function reportOrder(?array $source, ?string $placedAt, array $attributes = []): Order
{
    $meta = $source === null ? ['payment_option' => 'cash-on-delivery'] : ['traffic_source' => ['visit_id' => 1, ...$source, 'campaign' => null]];

    return Order::factory()->create(array_merge([
        'meta' => $meta,
        'placed_at' => $placedAt === null ? null : CarbonImmutable::parse($placedAt),
        'status' => 'payment-received',
        'total' => 10000,
        'shipping_total' => 1500,
    ], $attributes));
}

function facebookSocial(): array
{
    return ['source' => 'facebook', 'medium' => 'social'];
}

function googleOrganic(): array
{
    return ['source' => 'google', 'medium' => 'organic'];
}

/**
 * @param  array{source: string, medium: ?string}  $source
 */
function reportVisit(array $source, string $at, string $landingPath = '/'): TrafficVisit
{
    return TrafficVisit::factory()->create([
        ...$source,
        'landing_path' => $landingPath,
        'created_at' => CarbonImmutable::parse($at),
    ]);
}

/**
 * A cart created in the visit, stamped with its traffic source.
 */
function reportCartOf(TrafficVisit $visit, ?string $at = null): Cart
{
    return Cart::factory()->create([
        'meta' => ['traffic_source' => [
            'visit_id' => $visit->id,
            'source' => $visit->source,
            'medium' => $visit->medium,
            'campaign' => $visit->campaign,
        ]],
        'created_at' => $at === null ? $visit->created_at : CarbonImmutable::parse($at),
    ]);
}

/**
 * An order created from the cart, which copies its traffic source.
 *
 * @param  array<string, mixed>  $attributes
 */
function reportOrderOf(Cart $cart, ?string $placedAt, array $attributes = []): Order
{
    return Order::factory()->create(array_merge([
        'cart_id' => $cart->id,
        'meta' => ['traffic_source' => $cart->meta['traffic_source']],
        'placed_at' => $placedAt === null ? null : CarbonImmutable::parse($placedAt),
        'created_at' => $placedAt === null ? $cart->created_at : CarbonImmutable::parse($placedAt),
        'status' => 'payment-received',
        'total' => 10000,
        'shipping_total' => 1500,
    ], $attributes));
}

/**
 * Split the order as `SplitOrder` does: each new order gets an admin cart
 * without a source, `split_from_order_id`, the original's traffic source and
 * `placed_at` at the split; the original becomes `split`.
 *
 * @param  list<string>  $statuses  One new order per status.
 * @return list<Order>
 */
function splitReportOrder(Order $original, string $at, array $statuses): array
{
    $meta = $original->meta->getArrayCopy();

    $newOrders = array_map(fn (string $status): Order => Order::factory()->create([
        'cart_id' => Cart::factory()->create(['meta' => ['shippingType' => 'courier'], 'created_at' => CarbonImmutable::parse($at)])->id,
        'meta' => array_filter([
            'traffic_source' => $meta['traffic_source'] ?? null,
            'split_from_order_id' => $original->id,
        ]),
        'status' => $status,
        'placed_at' => CarbonImmutable::parse($at),
        'created_at' => CarbonImmutable::parse($at),
        'total' => 4000,
        'shipping_total' => 500,
    ]), $statuses);

    $original->meta = [...$meta, 'split_into_order_ids' => array_map(fn (Order $order): int => $order->id, $newOrders)];
    $original->status = 'split';
    $original->save();

    return $newOrders;
}

test('visits are split by source and medium', function () {
    reportVisits(60, 'google', 'organic', '2026-10-02 10:00');
    reportVisits(30, 'facebook', 'social', '2026-10-03 10:00');
    reportVisits(10, 'direct', 'none', '2026-10-04 10:00');

    $report = octoberReport();

    expect($report->visitsBySource())->toBe([
        ['source' => 'google', 'medium' => 'organic', 'visits' => 60],
        ['source' => 'facebook', 'medium' => 'social', 'visits' => 30],
        ['source' => 'direct', 'medium' => 'none', 'visits' => 10],
    ])->and($report->totals()['visits'])->toBe(100);
});

test('every day of the period is listed and a day without visits gives 0', function () {
    reportVisits(2, 'google', 'organic', '2026-10-01 08:00');
    reportVisits(3, 'google', 'organic', '2026-10-03 23:30');

    expect(octoberReport()->visitsPerDay())->toBe([
        '2026-10-01' => 2,
        '2026-10-02' => 0,
        '2026-10-03' => 3,
        '2026-10-04' => 0,
        '2026-10-05' => 0,
        '2026-10-06' => 0,
        '2026-10-07' => 0,
    ]);
});

test('visits over time are shown per day for 1 to 7 October, with 0 for a day without visits', function () {
    reportVisits(2, 'google', 'organic', '2026-10-01 08:00');
    reportVisits(3, 'google', 'organic', '2026-10-03 23:30');

    expect(octoberReport()->visitsOverTime())->toBe([
        'interval' => 'day',
        'visits' => [
            '2026-10-01' => 2,
            '2026-10-02' => 0,
            '2026-10-03' => 3,
            '2026-10-04' => 0,
            '2026-10-05' => 0,
            '2026-10-06' => 0,
            '2026-10-07' => 0,
        ],
    ]);
});

test('visits over time are shown for 14 weeks from 4 August to 8 November', function () {
    reportVisits(1, 'google', 'organic', '2026-08-03 23:59');
    reportVisits(2, 'google', 'organic', '2026-08-04 00:00');
    reportVisits(1, 'google', 'organic', '2026-08-09 23:59');
    reportVisits(4, 'google', 'organic', '2026-08-10 00:00');
    reportVisits(5, 'google', 'organic', '2026-11-08 12:00');
    reportVisits(1, 'google', 'organic', '2026-11-09 00:00');

    $overTime = (new TrafficReportQuery(CarbonImmutable::parse('2026-08-04'), CarbonImmutable::parse('2026-11-08')))->visitsOverTime();

    expect($overTime['interval'])->toBe('week')
        ->and($overTime['visits'])->toHaveCount(14)
        ->and(array_key_first($overTime['visits']))->toBe('2026-08-04')
        ->and(array_keys($overTime['visits'])[1])->toBe('2026-08-10')
        ->and(array_key_last($overTime['visits']))->toBe('2026-11-02')
        ->and($overTime['visits']['2026-08-04'])->toBe(3)
        ->and($overTime['visits']['2026-08-10'])->toBe(4)
        ->and($overTime['visits']['2026-08-17'])->toBe(0)
        ->and($overTime['visits']['2026-11-02'])->toBe(5);
});

test('visits over time are shown for 12 months from 1 January to 31 December', function () {
    reportVisits(1, 'google', 'organic', '2026-02-01 00:00');
    reportVisits(2, 'google', 'organic', '2026-02-28 23:59');

    $overTime = (new TrafficReportQuery(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31')))->visitsOverTime();

    expect($overTime['interval'])->toBe('month')
        ->and(array_keys($overTime['visits']))->toBe([
            '2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01',
            '2026-07-01', '2026-08-01', '2026-09-01', '2026-10-01', '2026-11-01', '2026-12-01',
        ])
        ->and($overTime['visits']['2026-01-01'])->toBe(0)
        ->and($overTime['visits']['2026-02-01'])->toBe(3);
});

test('the visits over time interval follows the period length', function (int $days, string $interval) {
    $from = CarbonImmutable::parse('2026-01-01');
    $report = new TrafficReportQuery($from, $from->addDays($days - 1));

    expect($report->visitsOverTimeInterval())->toBe($interval)
        ->and($report->visitsOverTime()['interval'])->toBe($interval);
})->with([
    '31 days' => [31, 'day'],
    '32 days' => [32, 'week'],
    '182 days' => [182, 'week'],
    '183 days' => [183, 'month'],
]);

test('visits over time leave out a visit whose order was moved to test-order', function () {
    reportOrderOf(reportCartOf(reportVisit(googleOrganic(), '2026-10-02 10:00')), '2026-10-02 11:00', ['status' => 'test-order']);
    reportVisits(2, 'google', 'organic', '2026-10-02 12:00');

    $overTime = (new TrafficReportQuery(CarbonImmutable::parse('2026-08-04'), CarbonImmutable::parse('2026-11-08')))->visitsOverTime();

    expect($overTime['visits']['2026-09-28'])->toBe(2)
        ->and(array_sum($overTime['visits']))->toBe(2);
});

test('visits, carts and orders outside the period are not counted', function () {
    foreach (['2026-09-30 23:59:59', '2026-10-08 00:00:00'] as $outside) {
        reportVisits(1, 'facebook', 'social', $outside);
        reportCart(facebookSocial(), $outside);
        reportOrder(facebookSocial(), $outside);
    }

    reportVisits(1, 'facebook', 'social', '2026-10-01 00:00:00');
    reportCart(facebookSocial(), '2026-10-07 23:59:59');
    reportOrder(facebookSocial(), '2026-10-07 23:59:59');

    expect(octoberReport()->totals())->toMatchArray([
        'visits' => 1,
        'carts' => 1,
        'orders' => 1,
    ]);
});

test('the facebook row shows its visits, carts, orders, order value and order rate', function () {
    reportVisits(30, 'facebook', 'social', '2026-10-02 10:00');
    reportVisits(10, 'google', 'organic', '2026-10-02 10:00');

    foreach (range(1, 6) as $i) {
        reportCart(facebookSocial(), '2026-10-03 10:00');
    }

    foreach (range(1, 3) as $i) {
        reportOrder(facebookSocial(), '2026-10-04 10:00', ['total' => 12000, 'shipping_total' => 2000]);
    }

    $facebook = collect(octoberReport()->funnel())->firstWhere('source', 'facebook');

    expect($facebook)->toMatchArray([
        'source' => 'facebook',
        'medium' => 'social',
        'visits' => 30,
        'carts' => 6,
        'orders' => 3,
        'order_value' => 30000,
    ])->and($facebook['order_rate'])->toBe(0.1);
});

test('the order value is the order total without shipping', function () {
    reportOrder(facebookSocial(), '2026-10-04 10:00', ['total' => 25000, 'shipping_total' => 1999]);

    expect(octoberReport()->ordersBySource())->toBe([
        ['source' => 'facebook', 'medium' => 'social', 'orders' => 1, 'order_value' => 23001],
    ]);
});

test('test orders and orders never placed are not counted', function () {
    reportOrder(facebookSocial(), '2026-10-04 10:00', ['status' => 'test-order']);
    reportOrder(facebookSocial(), null, ['status' => 'awaiting-payment', 'created_at' => CarbonImmutable::parse('2026-10-04 10:00')]);

    expect(octoberReport()->ordersBySource())->toBe([])
        ->and(octoberReport()->totals()['orders'])->toBe(0);
});

test('a split order counts once, as its original, with its visit and cart', function () {
    $original = reportOrderOf(reportCartOf(reportVisit(facebookSocial(), '2026-10-02 09:00')), '2026-10-02 10:00');
    splitReportOrder($original, '2026-10-03 10:00', ['payment-received', 'awaiting-payment']);

    $report = octoberReport();

    expect($report->ordersBySource())->toBe([
        ['source' => 'facebook', 'medium' => 'social', 'orders' => 1, 'order_value' => 8500],
    ])->and($report->totals())->toMatchArray([
        'visits' => 1,
        'carts' => 1,
        'orders' => 1,
        'order_value' => 8500,
    ]);
});

test('a split order whose new orders are all test orders is left out with its visit and cart', function () {
    $original = reportOrderOf(reportCartOf(reportVisit(facebookSocial(), '2026-10-02 09:00')), '2026-10-02 10:00');
    splitReportOrder($original, '2026-10-03 10:00', ['test-order', 'test-order']);

    expect(octoberReport()->totals())->toMatchArray([
        'visits' => 0,
        'carts' => 0,
        'orders' => 0,
        'order_value' => 0,
    ]);
});

test('ten visits with two split orders give five orders and an order rate of 50%', function () {
    $visits = collect(range(1, 10))->map(fn (): TrafficVisit => reportVisit(facebookSocial(), '2026-10-02 10:00'));
    $carts = $visits->take(7)->map(fn (TrafficVisit $visit): Cart => reportCartOf($visit));
    $orders = $carts->take(5)->map(fn (Cart $cart): Order => reportOrderOf($cart, '2026-10-03 10:00'))->values();

    splitReportOrder($orders[0], '2026-10-04 10:00', ['payment-received', 'payment-received']);
    splitReportOrder($orders[1], '2026-10-04 10:00', ['payment-received', 'payment-received', 'payment-received']);

    expect(octoberReport()->totals())->toMatchArray([
        'visits' => 10,
        'carts' => 7,
        'orders' => 5,
        'order_rate' => 0.5,
    ]);
});

test('an order placed on 30 September and split on 2 October counts in September only', function () {
    $original = reportOrderOf(reportCartOf(reportVisit(facebookSocial(), '2026-09-30 09:00')), '2026-09-30 10:00');
    splitReportOrder($original, '2026-10-02 10:00', ['payment-received', 'payment-received']);

    $september = new TrafficReportQuery(CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'));

    expect($september->totals())->toMatchArray([
        'visits' => 1,
        'carts' => 1,
        'orders' => 1,
        'order_value' => 8500,
    ])->and(octoberReport()->totals())->toMatchArray([
        'visits' => 0,
        'carts' => 0,
        'orders' => 0,
    ]);
});

test('a new order that is split again still counts its original once', function () {
    $original = reportOrderOf(reportCartOf(reportVisit(facebookSocial(), '2026-10-02 09:00')), '2026-10-02 10:00');
    [$splitAgain] = splitReportOrder($original, '2026-10-03 10:00', ['payment-received', 'test-order']);
    splitReportOrder($splitAgain, '2026-10-04 10:00', ['payment-received', 'payment-received']);

    $report = octoberReport();

    expect($report->ordersBySource())->toBe([
        ['source' => 'facebook', 'medium' => 'social', 'orders' => 1, 'order_value' => 8500],
    ])->and($report->totals())->toMatchArray([
        'visits' => 1,
        'carts' => 1,
        'orders' => 1,
    ]);
});

test('a visit whose order was moved to test-order is left out of every figure', function () {
    reportOrderOf(reportCartOf(reportVisit(googleOrganic(), '2026-10-02 10:00', '/sale')), '2026-10-02 11:00', ['status' => 'test-order']);
    reportVisits(2, 'google', 'organic', '2026-10-03 10:00');

    $report = octoberReport();

    expect($report->visitsBySource())->toBe([
        ['source' => 'google', 'medium' => 'organic', 'visits' => 2],
    ])->and($report->visitsPerDay())->toMatchArray([
        '2026-10-02' => 0,
        '2026-10-03' => 2,
    ])->and($report->landingPages())->toBe([
        ['landing_path' => '/', 'visits' => 2],
    ])->and($report->cartsBySource())->toBe([])
        ->and($report->ordersBySource())->toBe([])
        ->and($report->funnel())->toHaveCount(1)
        ->and($report->funnel()[0])->toMatchArray([
            'source' => 'google',
            'medium' => 'organic',
            'visits' => 2,
            'carts' => 0,
            'orders' => 0,
        ])->and($report->totals())->toMatchArray([
            'visits' => 2,
            'carts' => 0,
            'orders' => 0,
        ]);
});

test('a visit and a cart without an order are counted', function () {
    reportCartOf(reportVisit(googleOrganic(), '2026-10-02 10:00'));

    expect(octoberReport()->totals())->toMatchArray([
        'visits' => 1,
        'carts' => 1,
        'orders' => 0,
    ]);
});

test('an unplaced order awaiting payment keeps its visit and cart', function () {
    reportOrderOf(reportCartOf(reportVisit(facebookSocial(), '2026-10-02 10:00')), null, ['status' => 'awaiting-payment']);

    expect(octoberReport()->totals())->toMatchArray([
        'visits' => 1,
        'carts' => 1,
        'orders' => 0,
    ]);
});

test('a visit with a test order cart and a counted cart counts the visit and only the counted cart', function () {
    $visit = reportVisit(facebookSocial(), '2026-10-02 10:00');
    reportOrderOf(reportCartOf($visit), '2026-10-02 11:00', ['status' => 'test-order']);
    reportOrderOf(reportCartOf($visit, '2026-10-03 10:00'), '2026-10-03 11:00');

    expect(octoberReport()->totals())->toMatchArray([
        'visits' => 1,
        'carts' => 1,
        'orders' => 1,
    ]);
});

test('an order created after the period and moved to test-order leaves out the period\'s visit and cart', function () {
    reportOrderOf(reportCartOf(reportVisit(facebookSocial(), '2026-10-05 10:00')), '2026-10-09 10:00', ['status' => 'test-order']);

    expect(octoberReport()->totals())->toMatchArray([
        'visits' => 0,
        'carts' => 0,
        'orders' => 0,
    ]);
});

test('a status excluded from reports through config leaves out its visit and cart', function () {
    config(['lunar.orders.statuses.fraud' => ['label' => 'Fraud', 'exclude_from_reports' => true]]);

    reportOrderOf(reportCartOf(reportVisit(facebookSocial(), '2026-10-02 10:00')), '2026-10-02 11:00', ['status' => 'fraud']);
    reportCartOf(reportVisit(googleOrganic(), '2026-10-02 12:00'));

    $report = octoberReport();

    expect($report->visitsBySource())->toBe([
        ['source' => 'google', 'medium' => 'organic', 'visits' => 1],
    ])->and($report->totals())->toMatchArray([
        'visits' => 1,
        'carts' => 1,
        'orders' => 0,
    ]);
});

test('orders without a source form the unknown row, and carts without a source are not counted', function () {
    reportVisits(5, 'google', 'organic', '2026-10-02 10:00');
    reportCart(['source' => 'google', 'medium' => 'organic'], '2026-10-02 10:00');
    reportOrder(null, '2026-10-03 10:00');
    reportOrder(null, '2026-10-03 11:00');

    foreach (range(1, 3) as $i) {
        reportCart(null, '2026-10-03 10:00');
    }

    $report = octoberReport();
    $funnel = $report->funnel();
    $unknown = end($funnel);

    expect($unknown)->toBe([
        'key' => TrafficReportQuery::UNKNOWN_KEY,
        'source' => null,
        'medium' => null,
        'visits' => null,
        'carts' => null,
        'orders' => 2,
        'order_value' => 17000,
        'order_rate' => null,
    ])->and($report->totals())->toMatchArray([
        'visits' => 5,
        'carts' => 1,
        'orders' => 2,
        'attributed_orders' => 0,
        'order_rate' => 0.0,
    ]);
});

test('landing pages are ordered by visits', function () {
    reportVisits(25, 'google', 'organic', '2026-10-02 10:00', '/sale');
    reportVisits(40, 'google', 'organic', '2026-10-02 10:00', '/');
    reportVisits(3, 'google', 'organic', '2026-10-02 10:00', '/blog');

    $report = octoberReport();

    expect($report->landingPages())->toBe([
        ['landing_path' => '/', 'visits' => 40],
        ['landing_path' => '/sale', 'visits' => 25],
        ['landing_path' => '/blog', 'visits' => 3],
    ]);

    $page = $report->paginateLandingPages(perPage: 2, page: 2);

    expect($page->total())->toBe(3)
        ->and($page->items())->toBe([['landing_path' => '/blog', 'visits' => 3]]);
});
