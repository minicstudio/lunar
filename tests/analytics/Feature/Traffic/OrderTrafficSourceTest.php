<?php

use Filament\Facades\Filament;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ListOrders;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Analytics\Database\State\EnsureAnalyticsPermissions;
use Lunar\Analytics\Traffic\Filament\Extensions\ManageOrderExtension;
use Lunar\Analytics\Traffic\Filament\Extensions\OrderResourceExtension;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Tests\Analytics\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createLanguageAndCurrency();
    (new EnsureAnalyticsPermissions)->run();

    Filament::setCurrentPanel(Filament::getPanel('lunar'));
});

/**
 * A placed order with the given `meta.traffic_source`, and addresses so the order page renders.
 * The visit id defaults to 7, which no test creates.
 *
 * @param  ?array{visit_id?: int, source: string, medium: ?string, campaign?: ?string}  $source
 */
function trafficSourceOrder(?array $source): Order
{
    $country = Country::factory()->create();
    $meta = $source === null
        ? ['payment_option' => 'cash-on-delivery']
        : ['traffic_source' => ['visit_id' => 7, 'campaign' => null, ...$source]];

    return Order::factory()
        ->for(Customer::factory())
        ->has(OrderAddress::factory()->state(['type' => 'shipping', 'country_id' => $country->id]), 'shippingAddress')
        ->has(OrderAddress::factory()->state(['type' => 'billing', 'country_id' => $country->id]), 'billingAddress')
        ->create([
            'currency_code' => Currency::getDefault()->code,
            'placed_at' => now(),
            'meta' => $meta,
        ]);
}

dataset('staff who see the traffic source', [
    'admin without explicit permission' => [true, []],
    'staff managing orders with analytics:traffic-sources' => [false, ['sales:manage-orders', 'analytics:traffic-sources']],
]);

test('the plugin registers the order extensions', function () {
    $extensions = LunarPanel::getExtensions();

    expect(collect($extensions[OrderResource::class] ?? [])->map(fn (object $extension): string => $extension::class)->all())
        ->toContain(OrderResourceExtension::class)
        ->and(collect($extensions[ManageOrder::class] ?? [])->map(fn (object $extension): string => $extension::class)->all())
        ->toContain(ManageOrderExtension::class);
});

test('the order list shows the traffic source badge after the customer type', function (bool $admin, array $permissions) {
    $this->actingAs($this->makeStaff(admin: $admin, permissions: $permissions), 'staff');

    $googleAd = trafficSourceOrder(['source' => 'google', 'medium' => 'cpc', 'campaign' => 'Brand']);
    $withoutSource = trafficSourceOrder(null);

    $list = Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->assertTableColumnVisible('traffic_source')
        ->assertTableColumnExists('traffic_source', fn (TextColumn $column): bool => $column->isToggleable()
            && ! $column->isToggledHiddenByDefault()
            && ! $column->isSortable()
            && ! $column->isSearchable())
        ->assertTableColumnStateSet('traffic_source', 'Google · Paid ad', $googleAd)
        ->assertTableColumnStateSet('traffic_source', 'Unknown', $withoutSource)
        ->assertTableColumnExists('traffic_source', fn (TextColumn $column): bool => $column->getColor('Google · Paid ad') === 'warning'
            && $column->getTooltip() === 'Brand', $googleAd)
        ->assertSee('Google · Paid ad')
        ->assertSee('Unknown');

    $columnNames = array_keys($list->instance()->getTable()->getColumns());

    expect(array_search('traffic_source', $columnNames, true))->toBe(array_search('new_customer', $columnNames, true) + 1);
})->with('staff who see the traffic source');

test('the order page shows the traffic source and its visit in their own section', function (bool $admin, array $permissions) {
    $this->actingAs($this->makeStaff(admin: $admin, permissions: $permissions), 'staff');

    $visit = TrafficVisit::factory()->create([
        'source' => 'mailchimp',
        'medium' => 'email',
        'campaign' => 'Black-Friday',
        'landing_path' => '/sale',
        'created_at' => Carbon::parse('2026-11-02 08:15:00'),
    ]);
    $order = trafficSourceOrder(['visit_id' => $visit->id, 'source' => 'mailchimp', 'medium' => 'email', 'campaign' => 'Black-Friday']);
    $visitStarted = $visit->created_at->timezone(config('lunar.panel.timezone'))->format('Y-m-d h:i a');

    DB::enableQueryLog();

    Livewire::test(ManageOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentVisible('traffic_source')
        ->assertSchemaComponentExists('traffic_source.traffic_source_badge', checkComponentUsing: fn (TextEntry $entry): bool => $entry->getColor('Mailchimp · Email') === 'primary')
        ->assertSchemaComponentStateSet('traffic_source.traffic_source_badge', 'Mailchimp · Email')
        ->assertSchemaComponentStateSet('traffic_source.traffic_source_campaign', 'Black-Friday')
        ->assertSchemaComponentStateSet('traffic_source.traffic_source_landing_page', '/sale')
        ->assertSchemaComponentExists('traffic_source.traffic_source_landing_page', checkComponentUsing: fn (TextEntry $entry): bool => $entry->getUrl() === url('/sale')
            && $entry->shouldOpenUrlInNewTab())
        ->assertSchemaComponentHidden('traffic_source.traffic_source_referrer')
        ->assertSee('Mailchimp · Email')
        ->assertSee('Black-Friday')
        ->assertSee(url('/sale'))
        ->assertSee($visitStarted)
        ->assertSchemaComponentDoesNotExist('meta.meta_traffic_source');

    $visitQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'traffic_visits'));

    expect($visitQueries)->toHaveCount(1)
        ->and(collect(ManageOrder::getOrderSummarySchema())
            ->filter(fn (Component $component): bool => $component instanceof Entry)
            ->map(fn (Entry $entry): string => $entry->getName())
            ->filter(fn (string $name): bool => str_starts_with($name, 'traffic_source')))
        ->toBeEmpty();
})->with('staff who see the traffic source');

test('the order page shows the referrer and no campaign entry without a campaign', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    $visit = TrafficVisit::factory()->create([
        'source' => 'arukereso.hu',
        'medium' => 'referral',
        'referrer_host' => 'arukereso.hu',
        'landing_path' => '/telefonok',
    ]);
    $order = trafficSourceOrder(['visit_id' => $visit->id, 'source' => 'arukereso.hu', 'medium' => 'referral']);

    Livewire::test(ManageOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentStateSet('traffic_source.traffic_source_badge', 'arukereso.hu · Referral')
        ->assertSchemaComponentStateSet('traffic_source.traffic_source_referrer', 'arukereso.hu')
        ->assertSchemaComponentHidden('traffic_source.traffic_source_campaign')
        ->assertSee('arukereso.hu · Referral')
        ->assertDontSee('Campaign');
});

test('the order page shows only the unknown badge for an order without a traffic source', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    $order = trafficSourceOrder(null);

    $page = Livewire::test(ManageOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentVisible('traffic_source')
        ->assertSchemaComponentStateSet('traffic_source.traffic_source_badge', 'Unknown')
        ->assertSee('Unknown');

    foreach (['traffic_source.traffic_source_campaign', 'traffic_source.traffic_source_referrer', 'traffic_source.traffic_source_landing_page', 'traffic_source.traffic_source_visited_at'] as $entry) {
        $page->assertSchemaComponentHidden($entry);
    }
});

test('the order page shows the badge and campaign without visit entries when the visit row is missing', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    $order = trafficSourceOrder(['visit_id' => 404, 'source' => 'mailchimp', 'medium' => 'email', 'campaign' => 'Black-Friday']);

    $page = Livewire::test(ManageOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentStateSet('traffic_source.traffic_source_badge', 'Mailchimp · Email')
        ->assertSchemaComponentStateSet('traffic_source.traffic_source_campaign', 'Black-Friday');

    foreach (['traffic_source.traffic_source_referrer', 'traffic_source.traffic_source_landing_page', 'traffic_source.traffic_source_visited_at'] as $entry) {
        $page->assertSchemaComponentHidden($entry);
    }
});

test('the traffic source section sits between tags and additional info', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    $order = trafficSourceOrder(['source' => 'google', 'medium' => 'organic']);

    Livewire::test(ManageOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSeeInOrder([
            __('lunarpanel::order.infolist.tags.label'),
            'Traffic source',
            __('lunarpanel::order.infolist.additional_info.label'),
        ]);
});

test('staff who only manage orders see no traffic source on the order list or page', function () {
    $this->actingAs($this->makeStaff(permissions: ['sales:manage-orders']), 'staff');

    $order = trafficSourceOrder(['source' => 'mailchimp', 'medium' => 'email', 'campaign' => 'Black-Friday']);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->assertTableColumnHidden('traffic_source')
        ->assertDontSee('Mailchimp · Email');

    Livewire::test(ManageOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentHidden('traffic_source')
        ->assertDontSee('Traffic source')
        ->assertDontSee('Mailchimp · Email')
        ->assertDontSee('Black-Friday')
        ->assertDontSee('mailchimp')
        ->assertSchemaComponentDoesNotExist('meta.meta_traffic_source');
});

test('without the plugin the order list has no traffic source column', function () {
    // A panel without the analytics plugin: a fresh panel manager holds no analytics extensions.
    app()->forgetScopedInstances();
    LunarPanel::clearResolvedInstance('lunar-panel');

    $this->actingAs($this->makeStaff(admin: true), 'staff');
    trafficSourceOrder(['source' => 'google', 'medium' => 'cpc']);

    expect(LunarPanel::getExtensions())->not->toHaveKey(OrderResource::class);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->assertTableColumnDoesNotExist('traffic_source')
        ->assertDontSee('Google · Paid ad');
});
