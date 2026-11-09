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

test('a split original is not counted and its new orders are', function () {
    $original = reportOrder(facebookSocial(), '2026-10-02 10:00', ['status' => 'split']);
    reportOrder(facebookSocial(), '2026-10-03 10:00', ['meta' => ['split_from_order_id' => $original->id, 'traffic_source' => ['visit_id' => 1, ...facebookSocial(), 'campaign' => null]]]);
    reportOrder(facebookSocial(), '2026-10-03 10:00', ['meta' => ['split_from_order_id' => $original->id, 'traffic_source' => ['visit_id' => 1, ...facebookSocial(), 'campaign' => null]]]);

    expect(collect(octoberReport()->funnel())->firstWhere('source', 'facebook')['orders'])->toBe(2);
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
