<?php

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ListOrders;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Analytics\Database\State\EnsureAnalyticsPermissions;
use Lunar\Analytics\Traffic\Filament\Extensions\ManageOrderExtension;
use Lunar\Analytics\Traffic\Filament\Extensions\OrderResourceExtension;
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
 *
 * @param  ?array{source: string, medium: ?string, campaign?: ?string}  $source
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

test('the order page shows the traffic source badge and campaign in the summary', function (bool $admin, array $permissions) {
    $this->actingAs($this->makeStaff(admin: $admin, permissions: $permissions), 'staff');

    $order = trafficSourceOrder(['source' => 'mailchimp', 'medium' => 'email', 'campaign' => 'Black-Friday']);

    Livewire::test(ManageOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSchemaComponentStateSet('traffic_source', 'Mailchimp · Email')
        ->assertSchemaComponentStateSet('traffic_source_campaign', 'Black-Friday')
        ->assertSee('Traffic source')
        ->assertSee('Mailchimp · Email')
        ->assertSee('Black-Friday')
        ->assertSchemaComponentDoesNotExist('meta.meta_traffic_source');
})->with('staff who see the traffic source');

test('the order page shows no campaign entry without a campaign', function () {
    $this->actingAs($this->makeStaff(admin: true), 'staff');

    $order = trafficSourceOrder(['source' => 'direct', 'medium' => 'none']);

    Livewire::test(ManageOrder::class, ['record' => $order->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Direct')
        ->assertDontSee('Campaign');
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
