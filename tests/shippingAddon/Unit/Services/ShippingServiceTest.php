<?php

uses(\Lunar\Tests\shippingAddon\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\Addons\Shipping\Connectors\NominatimConnector;
use Lunar\Addons\Shipping\Enums\ShippingProviderEnum;
use Lunar\Addons\Shipping\Exceptions\OrderMissingShippingProviderException;
use Lunar\Addons\Shipping\Providers\Dpd\DpdApiClient;
use Lunar\Addons\Shipping\Providers\Dpd\DpdShippingProvider;
use Lunar\Addons\Shipping\Providers\Dpd\Requests\DownloadAWBPDF;
use Lunar\Addons\Shipping\Providers\Dpd\Requests\GenerateAWBRequest;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\GeocodeCountyRequest;
use Lunar\Addons\Shipping\Services\ShippingService;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdownItem;
use Lunar\DataTypes\Price;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Tests\Core\Stubs\User;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();
    $this->createUser();

    Config::set('lunar.shipping.enabled', true);
    Config::set('lunar.shipping.providers', ['dpd']);
    Config::set('lunar.shipping.dpd.enabled', true);
    Config::set('lunar.shipping.dpd.provider_class', DpdShippingProvider::class);
    Config::set('lunar.shipping.dpd.client_class', DpdApiClient::class);
    Config::set('lunar.shipping.dpd.base_url', 'https://dpd.test');
    Config::set('lunar.shipping.dpd.username', 'user');
    Config::set('lunar.shipping.dpd.password', 'pass');
    Config::set('lunar.shipping.dpd.service_id', 1);
    Config::set('lunar.shipping.dpd.contents', 'Goods');
    Config::set('lunar.shipping.dpd.package', 'BOX');
    Config::set('lunar.shipping.dpd.paper_size', 'A4');

    app()->singleton(DpdShippingProvider::class, fn () => new DpdShippingProvider(new DpdApiClient));
});

function makeOrderWithShippingBreakdown(): Order
{
    $user = User::where('email', 'test@example.com')->first();
    $country = Country::factory()->create();
    $customer = Customer::factory()->create();
    $group = CustomerGroup::where('default', true)->first();
    $currency = Currency::where('default', true)->first();
    $customer->customerGroups()->attach($group->id);
    $customer->users()->attach($user->id);

    $breakdown = new ShippingBreakdown(collect([
        new ShippingBreakdownItem(
            name: 'DPD Home',
            identifier: 'dpd_home',
            price: new Price(0, $currency, 1)
        ),
    ]));

    $order = Order::factory()
        ->for($customer)
        ->for($user)
        ->has(OrderAddress::factory()->state([
            'type' => 'billing',
            'contact_email' => 'billing@example.com',
            'country_id' => $country->id,
        ]), 'billingAddress')
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'city' => 'Arad',
            'postcode' => '310000',
            'contact_phone' => '+40700000000',
            'contact_email' => 'john@example.com',
            'line_one' => 'Street 1',
        ]), 'shippingAddress')
        ->create([
            'meta' => [
                'payment_type' => 'offline',
            ],
            'shipping_breakdown' => $breakdown,
        ]);

    return $order;
}

it('generateAWB stores awb number on order meta', function () {
    $mock = new MockClient([
        GenerateAWBRequest::class => MockResponse::make(['id' => 'AWB777'], 200),
    ]);

    $client = new DpdApiClient;
    $client->withMockClient($mock);
    app()->singleton(DpdShippingProvider::class, fn () => new DpdShippingProvider($client));

    $order = makeOrderWithShippingBreakdown();
    $service = new ShippingService;
    $service->generateAWB($order);

    $order->refresh();
    expect($order->meta['awb'] ?? null)->toBe('AWB777');
});

it('generateAWB works with hyphenated identifier', function () {
    $mock = new MockClient([
        GenerateAWBRequest::class => MockResponse::make(['id' => 'AWB1234'], 200),
    ]);

    $client = new DpdApiClient;
    $client->withMockClient($mock);
    app()->singleton(DpdShippingProvider::class, fn () => new DpdShippingProvider($client));

    $user = $this->createUser();
    $country = Country::factory()->create();
    $customer = Customer::factory()->create();
    $group = CustomerGroup::where('default', true)->first();
    $currency = Currency::where('default', true)->first();
    $customer->customerGroups()->attach($group->id);
    $customer->users()->attach($user->id);

    $breakdown = new ShippingBreakdown(collect([
        new ShippingBreakdownItem(
            name: 'DPD Home',
            identifier: 'dpd-home',
            price: new Price(0, $currency, 1)
        ),
    ]));

    $order = Order::factory()
        ->for($customer)
        ->for($user)
        ->has(OrderAddress::factory()->state([
            'type' => 'billing',
            'contact_email' => 'billing@example.com',
            'country_id' => $country->id,
        ]), 'billingAddress')
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'city' => 'Arad',
            'postcode' => '310000',
            'contact_phone' => '+40700000000',
            'contact_email' => 'john@example.com',
            'line_one' => 'Street 1',
        ]), 'shippingAddress')
        ->create([
            'meta' => [
                'payment_type' => 'offline',
            ],
            'shipping_breakdown' => $breakdown,
        ]);

    $service = new ShippingService;
    $service->generateAWB($order);

    $order->refresh();
    expect($order->meta['awb'] ?? null)->toBe('AWB1234');
});

it('downloadAWBPDF proxies to provider and returns response', function () {
    $mock = new MockClient([
        DownloadAWBPDF::class => MockResponse::make('%PDF-1.7 ...', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $client = new DpdApiClient;
    $client->withMockClient($mock);
    app()->singleton(DpdShippingProvider::class, fn () => new DpdShippingProvider($client));

    $order = makeOrderWithShippingBreakdown();
    $meta = $order->meta ?? [];
    $meta['awb'] = 'AWB999';
    $order->meta = $meta;
    $order->save();

    $service = new ShippingService;
    $resp = $service->downloadAWBPDF($order);
    expect($resp->successful())->toBeTrue();
});

it('getCounties / getCities / getLockers return collections (empty for DPD)', function () {
    $service = new ShippingService;
    $counties = $service->getCounties(ShippingProviderEnum::dpd);
    $cities = $service->getCities(ShippingProviderEnum::dpd, 1);
    $lockers = $service->getLockers(ShippingProviderEnum::dpd, 1, 1);

    expect($counties->toArray())->toBe([])
        ->and($cities->toArray())->toBe([])
        ->and($lockers->toArray())->toBe([]);
});

it('getLatLngOfLocation returns first result coords from Nominatim', function () {
    $mock = new MockClient([
        GeocodeCountyRequest::class => MockResponse::make([
            ['lat' => '46.7693790', 'lon' => '23.5899542'],
        ], 200),
    ]);

    $connector = new NominatimConnector;
    $connector->withMockClient($mock);
    app()->singleton(NominatimConnector::class, fn () => $connector);

    $service = new ShippingService;
    $coords = $service->getLatLngOfLocation('Cluj');
    expect($coords)->toBe(['lat' => '46.7693790', 'lng' => '23.5899542']);
});

it('getLatLngOfLocation returns null lat/lng when response is empty', function () {
    $mock = new MockClient([
        GeocodeCountyRequest::class => MockResponse::make([], 200),
    ]);

    $connector = new NominatimConnector;
    $connector->withMockClient($mock);
    app()->singleton(NominatimConnector::class, fn () => $connector);

    $service = new ShippingService;
    $coords = $service->getLatLngOfLocation('Nowhere');
    expect($coords)->toBe(['lat' => null, 'lng' => null]);
});

it('throws when order has no shipping provider selected', function () {
    $user = $this->createUser();
    $country = Country::factory()->create();
    $customer = Customer::factory()->create();
    $group = CustomerGroup::where('default', true)->first();
    $customer->customerGroups()->attach($group->id);
    $customer->users()->attach($user->id);

    $order = Order::factory()
        ->for($customer)
        ->for($user)
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'city' => 'Arad',
            'postcode' => '310000',
            'contact_phone' => '+40700000000',
            'contact_email' => 'john@example.com',
            'line_one' => 'Street 1',
            'country_id' => $country->id,
        ]), 'shippingAddress')
        ->create([
            'meta' => [
                'payment_type' => 'offline',
            ],
        ]);

    $service = new ShippingService;
    expect(fn () => $service->generateAWB($order))
        ->toThrow(OrderMissingShippingProviderException::class);
});
