<?php

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Pages\Dashboard;
use Lunar\Analytics\Conversions\Enums\ConversionEventType;
use Lunar\Analytics\Conversions\Filament\Pages\ConversionsPage;
use Lunar\Analytics\Conversions\Filament\Widgets\ConversionFunnelChart;
use Lunar\Analytics\Conversions\Filament\Widgets\ConversionsOverTimeChart;
use Lunar\Analytics\Conversions\Filament\Widgets\ConversionStatsOverview;
use Lunar\Analytics\Conversions\Models\ConversionEvent;
use Lunar\Analytics\Database\State\EnsureAnalyticsPermissions;
use Lunar\Models\Cart;
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
 * Navigation items the current staff user sees in the Analytics group, by url.
 *
 * @return array<string, string>
 */
function conversionAnalyticsNavigation(): array
{
    return collect(Filament::getNavigation())
        ->filter(fn (NavigationGroup $group): bool => $group->getLabel() === 'Analytics')
        ->flatMap(fn (NavigationGroup $group) => $group->getItems())
        ->mapWithKeys(fn (NavigationItem $item): array => [(string) $item->getUrl() => $item->getLabel()])
        ->all();
}

function conversionFilters(string $from = '2026-10-01', string $to = '2026-10-07'): array
{
    return ['pageFilters' => ['from' => $from, 'to' => $to]];
}

/**
 * 120 adds to cart, 50 carts, 20 checkouts and 10 purchases on 1 to 7 October.
 * 8 purchases are from tracked carts, whose checkout starts are among the 20.
 */
function seedConversionReport(): void
{
    foreach ([[ConversionEventType::AddToCart, 120], [ConversionEventType::CartStarted, 50], [ConversionEventType::BeginCheckout, 12]] as [$type, $count]) {
        ConversionEvent::factory()->count($count)->create(['type' => $type, 'cart_id' => 9000, 'created_at' => '2026-10-02 10:00:00']);
    }

    $purchase = fn (?int $cartId): Order => Order::factory()->create([
        'cart_id' => $cartId,
        'meta' => ['payment_option' => 'cash-on-delivery'],
        'status' => 'payment-received',
        'placed_at' => '2026-10-04 10:00:00',
    ]);

    foreach (Cart::factory()->count(8)->create() as $cart) {
        ConversionEvent::factory()->beginCheckout()->create(['cart_id' => $cart->id, 'created_at' => '2026-10-02 10:00:00']);
        $purchase($cart->id);
    }

    $purchase(null);
    $purchase(null);
}

test('an admin and a staff member with the permission open the report from the Analytics navigation', function (bool $admin, array $permissions) {
    $this->actingAs($this->makeStaff(admin: $admin, permissions: $permissions), 'staff');

    expect(ConversionsPage::canAccess())->toBeTrue()
        ->and(conversionAnalyticsNavigation())->toHaveKey(ConversionsPage::getUrl())
        ->and(conversionAnalyticsNavigation()[ConversionsPage::getUrl()])->toBe('Conversions');

    $this->get(ConversionsPage::getUrl())->assertOk();
})->with([
    'admin without explicit permission' => [true, []],
    'staff with analytics:conversions' => [false, ['analytics:conversions']],
]);

test('a staff member with only the traffic report permission is refused and does not see the report', function () {
    $this->actingAs($this->makeStaff(permissions: ['analytics:traffic-sources']), 'staff');

    expect(ConversionsPage::canAccess())->toBeFalse()
        ->and(conversionAnalyticsNavigation())->not->toHaveKey(ConversionsPage::getUrl());

    $this->get(ConversionsPage::getUrl())->assertForbidden();
});

test('the report sits at analytics/conversions with a funnel icon', function () {
    expect(ConversionsPage::getUrl())->toEndWith('/analytics/conversions')
        ->and(ConversionsPage::getNavigationGroup())->toBe('Analytics')
        ->and(ConversionsPage::getNavigationLabel())->toBe('Conversions')
        ->and(ConversionsPage::getNavigationSort())->toBe(2);
});

test('the default period is today minus 29 days to today', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    Livewire::test(ConversionsPage::class)
        ->assertSet('filters.from', '2026-09-16')
        ->assertSet('filters.to', '2026-10-15');

    [$from, $to] = ConversionsPage::periodFromFilters(null);

    expect($from->toDateString())->toBe('2026-09-16')
        ->and($to->toDateString())->toBe('2026-10-15');
});

test('the stats overview shows the period totals and rates', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');
    seedConversionReport();

    Livewire::test(ConversionStatsOverview::class, conversionFilters())
        ->assertSeeInOrder([
            'Add to cart', '120',
            'Carts', '50',
            'Checkouts', '20',
            'Purchases', '10', '8 from tracked carts',
            'Checkout rate', '40.0%', 'Checkouts per cart',
            'Purchase rate', '40.0%', 'Purchases from tracked carts per checkout',
            'Cart purchase rate', '16.0%', 'Purchases from tracked carts per cart',
        ]);
});

test('a rate without a value shows a dash', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    Livewire::test(ConversionStatsOverview::class, conversionFilters())
        ->assertSeeInOrder([
            'Checkout rate', '—',
            'Purchase rate', '—',
            'Cart purchase rate', '—',
        ]);
});

test('the funnel chart sits between the stats and the chart over time', function () {
    expect(ConversionsPage::conversionWidgets())->toBe([
        ConversionStatsOverview::class,
        ConversionFunnelChart::class,
        ConversionsOverTimeChart::class,
    ]);
});

test('the funnel chart shows the period totals with only the purchases from tracked carts', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');
    seedConversionReport();

    $funnel = Livewire::test(ConversionFunnelChart::class, conversionFilters())
        ->assertSee('Conversion funnel')
        ->assertSet('options.plotOptions.bar.isFunnel', true)
        ->assertSet('options.xaxis.categories', ['Add to cart', 'Carts', 'Checkouts', 'Purchases from tracked carts']);

    expect($funnel->get('options.series'))->toHaveCount(1)
        ->and($funnel->get('options.series.0.data'))->toBe([120, 50, 20, 8]);
});

test('the chart shows the four steps, with only the purchases from tracked carts, and a heading per interval', function (string $from, string $to, string $heading, string $firstLabel) {
    $this->actingAs($this->makeStaff(admin: true), 'staff');
    seedConversionReport();

    $chart = Livewire::test(ConversionsOverTimeChart::class, conversionFilters($from, $to))
        ->assertSee($heading)
        ->assertSet('options.xaxis.categories.0', $firstLabel);

    expect(array_column($chart->get('options.series'), 'name'))->toBe(['Add to cart', 'Carts', 'Checkouts', 'Purchases from tracked carts'])
        ->and(array_map('array_sum', array_column($chart->get('options.series'), 'data')))->toBe([120, 50, 20, 8]);
})->with([
    'a week, per day' => ['2026-10-01', '2026-10-07', 'Funnel per day', '1 Oct'],
    'three months, per week' => ['2026-08-04', '2026-11-08', 'Funnel per week', '4 Aug'],
    'a year, per month' => ['2026-01-01', '2026-12-31', 'Funnel per month', 'Jan 2026'],
]);

test('the lunar dashboard shows none of the conversion widgets', function () {
    expect(array_intersect((new Dashboard)->getWidgets(), ConversionsPage::conversionWidgets()))->toBe([]);
});
