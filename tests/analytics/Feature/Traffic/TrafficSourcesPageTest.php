<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Pages\Dashboard;
use Lunar\Analytics\Database\State\EnsureAnalyticsPermissions;
use Lunar\Analytics\Traffic\Filament\Pages\TrafficSourcesPage;
use Lunar\Analytics\Traffic\Filament\Widgets\LandingPagesTable;
use Lunar\Analytics\Traffic\Filament\Widgets\TrafficSourceFunnelTable;
use Lunar\Analytics\Traffic\Filament\Widgets\TrafficStatsOverview;
use Lunar\Analytics\Traffic\Filament\Widgets\VisitsBySourceChart;
use Lunar\Analytics\Traffic\Filament\Widgets\VisitsOverTimeChart;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Analytics\Traffic\Reports\TrafficReportQuery;
use Lunar\DataTypes\Price;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    (new EnsureAnalyticsPermissions)->run();

    Filament::setCurrentPanel(Filament::getPanel('lunar'));

    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
});

/**
 * Navigation urls the current staff user sees on the lunar panel.
 *
 * @return list<string>
 */
function trafficNavigationUrls(): array
{
    return collect(Filament::getNavigation())
        ->flatMap(fn (NavigationGroup $group) => $group->getItems())
        ->map(fn (NavigationItem $item): ?string => $item->getUrl())
        ->filter()
        ->values()
        ->all();
}

function seedTrafficReport(): void
{
    $facebook = ['visit_id' => 1, 'source' => 'facebook', 'medium' => 'social', 'campaign' => null];

    TrafficVisit::factory()->count(60)->create(['source' => 'google', 'medium' => 'organic', 'landing_path' => '/', 'created_at' => '2026-10-02 10:00:00']);
    TrafficVisit::factory()->count(30)->create(['source' => 'facebook', 'medium' => 'social', 'landing_path' => '/sale', 'created_at' => '2026-10-03 10:00:00']);
    TrafficVisit::factory()->count(10)->create(['source' => 'direct', 'medium' => 'none', 'landing_path' => '/blog', 'created_at' => '2026-10-03 10:00:00']);

    foreach (range(1, 6) as $i) {
        Cart::factory()->create(['meta' => ['traffic_source' => $facebook], 'created_at' => '2026-10-04 10:00:00']);
    }

    foreach (range(1, 3) as $i) {
        Order::factory()->create([
            'meta' => ['traffic_source' => $facebook],
            'status' => 'payment-received',
            'placed_at' => '2026-10-05 10:00:00',
            'total' => 12000,
            'shipping_total' => 2000,
        ]);
    }
}

function octoberFilters(): array
{
    return ['pageFilters' => ['from' => '2026-10-01', 'to' => '2026-10-07']];
}

test('an admin and a staff member with the permission can open the report', function (bool $admin, array $permissions) {
    $this->actingAs($this->makeStaff(admin: $admin, permissions: $permissions), 'staff');

    expect(TrafficSourcesPage::canAccess())->toBeTrue()
        ->and(trafficNavigationUrls())->toContain(TrafficSourcesPage::getUrl());

    $this->get(TrafficSourcesPage::getUrl())->assertOk();
})->with([
    'admin without explicit permission' => [true, []],
    'staff with analytics:traffic-sources' => [false, ['analytics:traffic-sources']],
]);

test('a staff member without the permission is refused and sees no navigation item', function () {
    $this->actingAs($this->makeStaff(permissions: ['sales:manage-orders']), 'staff');

    expect(TrafficSourcesPage::canAccess())->toBeFalse()
        ->and(trafficNavigationUrls())->not->toContain(TrafficSourcesPage::getUrl());

    $this->get(TrafficSourcesPage::getUrl())->assertForbidden();
});

test('the report sits in the analytics navigation group', function () {
    expect(TrafficSourcesPage::getNavigationGroup())->toBe('Analytics')
        ->and(TrafficSourcesPage::getNavigationLabel())->toBe('Traffic sources');
});

test('the default period is today minus 29 days to today', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    Livewire::test(TrafficSourcesPage::class)
        ->assertSet('filters.from', '2026-09-16')
        ->assertSet('filters.to', '2026-10-15');

    [$from, $to] = TrafficSourcesPage::periodFromFilters(null);

    expect($from->toDateString())->toBe('2026-09-16')
        ->and($to->toDateString())->toBe('2026-10-15');
});

test('the stats overview shows the period totals', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');
    seedTrafficReport();

    $orderValue = (string) (new Price(30000, Currency::getDefault(), 1))->formatted();

    Livewire::test(TrafficStatsOverview::class, octoberFilters())
        ->assertSee('Visits')
        ->assertSee('100')
        ->assertSee('Carts')
        ->assertSee('6')
        ->assertSee($orderValue)
        ->assertSee('3.0%');
});

test('the visits by source chart shows the split', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');
    seedTrafficReport();

    Livewire::test(VisitsBySourceChart::class, octoberFilters())
        ->assertSet('options.series', [60, 30, 10])
        ->assertSet('options.labels', ['Google · Organic search', 'Facebook · Social', 'Direct'])
        ->assertSet('options.colors', array_slice(VisitsBySourceChart::COLORS, 0, 3));
});

test('the report cuts a long source in the chart and wraps it in the table', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    $source = str_repeat('a', 120);
    TrafficVisit::factory()->create(['source' => $source, 'medium' => 'referral', 'created_at' => '2026-10-02 10:00:00']);

    Livewire::test(VisitsBySourceChart::class, octoberFilters())
        ->assertSet('options.labels', ['A'.str_repeat('a', VisitsBySourceChart::LABEL_LENGTH - 1).'…']);

    Livewire::test(TrafficSourceFunnelTable::class, octoberFilters())
        ->assertTableColumnStateSet('source', 'A'.str_repeat('a', 119), "{$source}\0referral")
        ->assertSeeHtml('style="max-width: 20rem; overflow-wrap: break-word;"');
});

test('the visits by source chart escapes source names, which the chart renders as HTML', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    TrafficVisit::factory()->create(['source' => '<img src=x.png onerror=alert(1)>', 'medium' => 'none', 'created_at' => '2026-10-02 10:00:00']);

    Livewire::test(VisitsBySourceChart::class, octoberFilters())
        ->assertSet('options.labels', ['&lt;img src=x.png onerror=alert(1)&gt;']);
});

test('the report names sources and mediums in words', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    TrafficVisit::factory()->count(3)->create(['source' => 'google', 'medium' => 'cpc', 'created_at' => '2026-10-02 10:00:00']);
    TrafficVisit::factory()->count(2)->create(['source' => 'facebook', 'medium' => 'social', 'created_at' => '2026-10-02 10:00:00']);
    TrafficVisit::factory()->create(['source' => 'direct', 'medium' => 'none', 'created_at' => '2026-10-02 10:00:00']);
    Order::factory()->create(['meta' => ['payment_option' => 'cash-on-delivery'], 'status' => 'payment-received', 'placed_at' => '2026-10-05 11:00:00']);

    Livewire::test(VisitsBySourceChart::class, octoberFilters())
        ->assertSet('options.labels', ['Google · Paid ad', 'Facebook · Social', 'Direct']);

    Livewire::test(TrafficSourceFunnelTable::class, octoberFilters())
        ->assertTableColumnStateSet('source', 'Google', "google\0cpc")
        ->assertTableColumnStateSet('medium', 'Paid ad', "google\0cpc")
        ->assertTableColumnStateSet('source', 'Facebook', "facebook\0social")
        ->assertTableColumnStateSet('medium', 'Social', "facebook\0social")
        ->assertTableColumnStateSet('source', 'Direct', "direct\0none")
        ->assertTableColumnStateSet('medium', null, "direct\0none")
        ->assertTableColumnStateSet('source', null, TrafficReportQuery::UNKNOWN_KEY)
        ->assertSeeInOrder(['Google', 'Paid ad', 'Facebook', 'Social', 'Direct', 'Unknown']);
});

test('the visits by source chart groups sources beyond the top eight as other', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    foreach (range(1, 10) as $i) {
        TrafficVisit::factory()->count(11 - $i)->create(['source' => "source-{$i}", 'medium' => 'referral', 'created_at' => '2026-10-02 10:00:00']);
    }

    $data = Livewire::test(VisitsBySourceChart::class, octoberFilters())->instance()->chartData();

    expect($data['labels'])->toHaveCount(9)
        ->and(end($data['labels']))->toBe('Other')
        ->and(end($data['series']))->toBe(2 + 1)
        ->and($data['colors'])->toBe([...VisitsBySourceChart::COLORS, VisitsBySourceChart::OTHER_COLOR]);
});

test('the visits over time chart shows every day of a week-long period', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');
    seedTrafficReport();

    Livewire::test(VisitsOverTimeChart::class, octoberFilters())
        ->assertSee('Visits per day')
        ->assertSet('options.series.0.data', [0, 60, 40, 0, 0, 0, 0])
        ->assertSet('options.xaxis.categories.0', '1 Oct')
        ->assertSet('options.xaxis.categories.6', '7 Oct');
});

test('the visits over time chart shows weeks for 4 August to 8 November', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');
    seedTrafficReport();

    $chart = Livewire::test(VisitsOverTimeChart::class, ['pageFilters' => ['from' => '2026-08-04', 'to' => '2026-11-08']])
        ->assertSee('Visits per week')
        ->assertDontSee('Visits per day')
        ->assertSet('options.xaxis.categories.0', '4 Aug')
        ->assertSet('options.xaxis.categories.1', '10 Aug');

    expect($chart->get('options.series.0.data'))->toHaveCount(14)
        ->and(array_sum($chart->get('options.series.0.data')))->toBe(100);
});

test('the visits over time chart shows months for a year', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    Livewire::test(VisitsOverTimeChart::class, ['pageFilters' => ['from' => '2026-01-01', 'to' => '2026-12-31']])
        ->assertSee('Visits per month')
        ->assertSet('options.xaxis.categories.0', 'Jan 2026')
        ->assertCount('options.xaxis.categories', 12);
});

test('the funnel table shows each source with its order rate and the unknown row', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');
    seedTrafficReport();
    Order::factory()->create([
        'meta' => ['payment_option' => 'cash-on-delivery'],
        'status' => 'payment-received',
        'placed_at' => '2026-10-05 11:00:00',
    ]);

    Livewire::test(TrafficSourceFunnelTable::class, octoberFilters())
        ->assertSeeInOrder(['Google', 'Organic search', 'Facebook', 'Social', '30', '6', '3', '10.0%', 'Direct', 'Unknown']);
});

test('the landing pages table lists the most visited first', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');
    seedTrafficReport();

    Livewire::test(LandingPagesTable::class, octoberFilters())
        ->assertSeeInOrder(['/sale', '30', '/blog', '10']);
});

test('the lunar dashboard shows none of the traffic widgets', function () {
    $widgets = (new Dashboard)->getWidgets();

    expect(array_intersect($widgets, TrafficSourcesPage::trafficWidgets()))->toBe([]);
});
