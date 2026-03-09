<?php

uses(\Lunar\Tests\ShippingAddon\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;
use Lunar\Addons\Shipping\Contracts\TokenAwareShippingApiClient;
use Lunar\Addons\Shipping\Enums\ShippingType;
use Lunar\Addons\Shipping\Exceptions\FailedAWBGenerationException;
use Lunar\Addons\Shipping\Models\ShippingCity;
use Lunar\Addons\Shipping\Models\ShippingCounty;
use Lunar\Addons\Shipping\Models\ShippingLocker;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\AuthenticateRequest;
use Lunar\Addons\Shipping\Providers\Sameday\Requests\DownloadAWBPDF;
use Lunar\Addons\Shipping\Providers\Sameday\SamedayApiClient;
use Lunar\Addons\Shipping\Providers\Sameday\SamedayShippingProvider;
use Lunar\Addons\Shipping\Providers\Sameday\SamedayTokenProvider;
use Lunar\Models\Country;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

/**
 * Helper method to create a mocked API client for SamedayShippingProvider tests
 */
function createMockedSamedayApiClientForShippingProvider(array $mockResponses): SamedayApiClient
{
    $mock = new MockClient($mockResponses);
    $client = new SamedayApiClient;

    // Override HTTP config to disable SSL verification for testing
    $client->config()->add('verify', false);
    $client->withMockClient($mock);

    return $client;
}

beforeEach(function () {
    $this->createCurrencies();
    Config::set('lunar.shipping.enabled', true);
    Config::set('lunar.shipping.providers', ['sameday']);
    Config::set('lunar.shipping.sameday.enabled', true);
    Config::set('lunar.shipping.sameday.base_url', 'https://sameday.test');
    Config::set('lunar.shipping.sameday.username', 'user');
    Config::set('lunar.shipping.sameday.password', 'pass');
    Config::set('lunar.shipping.sameday.pickup_point_id', 10);
    Config::set('lunar.shipping.sameday.contact_person_id', 20);
    Config::set('lunar.shipping.sameday.home_shipping_id', 7);
    Config::set('lunar.shipping.sameday.locker_shipping_id', 15);
    Config::set('lunar.shipping.sameday.personal_drop_off', true);
});

it('SamedayShippingProvider throws when response has no awbNumber', function () {
    $client = \Mockery::mock(TokenAwareShippingApiClient::class);
    $client->shouldReceive('generateAWB')
        ->once()
        ->with(\Mockery::type(AWBRequestBodyInterface::class))
        ->andReturn(['message' => 'Bad request']);

    $order = new Order;
    $order->reference = 'ORD-1';
    $order->meta = ['payment_type' => 'online', 'shippingType' => ShippingType::COURIER->value];
    $order->setRelation('productLines', collect());
    $order->setRelation('shippingAddress', new OrderAddress([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'city' => 'CJ',
        'state' => 'Cluj',
        'postcode' => '400000',
        'contact_phone' => '+40711111111',
        'contact_email' => 'jane@example.com',
        'line_one' => 'Street 2',
    ]));

    $provider = new SamedayShippingProvider($client);
    $this->expectException(FailedAWBGenerationException::class);
    $provider->generateAWB($order);
});

it('isEnabled reflects config flag', function () {
    $client = \Mockery::mock(TokenAwareShippingApiClient::class);
    $provider = new SamedayShippingProvider($client);

    Config::set('lunar.shipping.sameday.enabled', true);
    expect($provider->isEnabled())->toBeTrue();

    Config::set('lunar.shipping.sameday.enabled', false);
    expect($provider->isEnabled())->toBeFalse();
});

it('generateAWB returns awbNumber and builds recipient payload (courier)', function () {
    $captured = [];
    $client = \Mockery::mock(TokenAwareShippingApiClient::class);
    $client->shouldReceive('generateAWB')
        ->once()
        ->with(\Mockery::on(function (AWBRequestBodyInterface $payload) use (&$captured) {
            $captured = $payload->toArray();

            return true;
        }))
        ->andReturn(['awbNumber' => 'SAM123']);

    $provider = new SamedayShippingProvider($client);

    $order = new Order;
    $order->reference = 'ORDER123';
    $order->packageWeight = 0;
    $order->meta = ['payment_type' => 'online', 'shippingType' => ShippingType::COURIER->value];
    $order->setRelation('productLines', collect());
    $order->setRelation('shippingAddress', new OrderAddress([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'company_name' => null,
        'city' => 'Arad',
        'state' => 'Arad',
        'postcode' => '310000',
        'contact_phone' => '+40700000000',
        'contact_email' => 'john@example.com',
        'line_one' => 'Street 1',
        'line_two' => null,
        'line_three' => null,
        'delivery_instructions' => 'Leave at door please',
        'meta' => [],
    ]));

    $resp = $provider->generateAWB($order);
    expect($resp['awbNumber'])->toBe('SAM123');

    expect($captured)->toHaveKeys([
        'pickupPoint',
        'packageType',
        'packageWeight',
        'service',
        'serviceTaxes',
        'awbPayment',
        'cashOnDelivery',
        'insuredValue',
        'thirdPartyPickup',
        'awbRecipient',
        'parcels',
        'contactPerson',
        'packageNumber',
        'clientInternalReference',
        'observation',
        'oohLastMile',
    ])
        ->and($captured['pickupPoint'])->toBe(10)
        ->and($captured['packageType'])->toBe(0)
        ->and($captured['packageWeight'])->toBe(1.0)
        ->and($captured['service'])->toBe(7)
        ->and($captured['serviceTaxes'])->toBe(['PDO'])
        ->and($captured['awbPayment'])->toBe('1')
        ->and($captured['cashOnDelivery'])->toBe(0.0)
        ->and($captured['insuredValue'])->toBe(0.0)
        ->and($captured['thirdPartyPickup'])->toBe(0)
        ->and($captured['awbRecipient'])->toMatchArray([
            'name' => 'John Doe',
            'phoneNumber' => '+40700000000',
            'personType' => 0,
            'companyName' => null,
            'postalCode' => '310000',
            'countyString' => 'Arad',
            'cityString' => 'Arad',
            'address' => 'Street 1  ',
            'email' => 'john@example.com',
        ])
        ->and($captured['parcels'][0]['weight'])->toBe(1.0)
        ->and($captured['contactPerson'])->toBe(20)
        ->and($captured['packageNumber'])->toBe(1)
        ->and($captured['clientInternalReference'])->toBe('ORDER123')
        ->and($captured['oohLastMile'])->toBeNull();
});

it('generateAWB builds locker payload when shippingType is LOCKER', function () {
    $captured = [];
    $client = \Mockery::mock(TokenAwareShippingApiClient::class);
    $client->shouldReceive('generateAWB')
        ->once()
        ->with(\Mockery::on(function (AWBRequestBodyInterface $payload) use (&$captured) {
            $captured = $payload->toArray();

            return true;
        }))
        ->andReturn(['awbNumber' => 'SAM999']);

    $provider = new SamedayShippingProvider($client);

    $order = new Order;
    $order->reference = 'ORDER-L1';
    $order->meta = [
        'payment_type' => 'offline',
        'shippingType' => ShippingType::LOCKER->value,
    ];
    $order->setRelation('productLines', collect());
    $order->setRelation('shippingAddress', new OrderAddress([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'company_name' => 'ACME SRL',
        'city' => 'Cluj',
        'state' => 'Cluj',
        'postcode' => '400000',
        'contact_phone' => '+40711111111',
        'contact_email' => 'jane@example.com',
        'line_one' => 'Street 2',
        'meta' => ['locker_id' => 12345],
    ]));

    $provider->generateAWB($order);

    expect($captured['service'])->toBe(15)
        ->and($captured['oohLastMile'])->toBe(12345)
        ->and($captured['awbRecipient']['personType'])->toBe(1)
        ->and($captured['cashOnDelivery'])->toBeFloat();
});

it('downloadAWBPDF returns successful response', function () {
    $client = createMockedSamedayApiClientForShippingProvider([
        AuthenticateRequest::class => MockResponse::make(['token' => 'tok', 'expire_at' => '2030-01-01 00:00:00'], 200),
        DownloadAWBPDF::class => MockResponse::make('%PDF-1.7 ...', 200, ['Content-Type' => 'application/pdf']),
    ]);

    // Also bind a mocked token provider to avoid container issues in parallel execution
    $tokenProvider = new SamedayTokenProvider($client);
    app()->bind(SamedayTokenProvider::class, fn () => $tokenProvider);

    $provider = new SamedayShippingProvider($client);
    $resp = $provider->downloadAWBPDF('AWB123');
    expect($resp->successful())->toBeTrue();
});

it('getCounties, getCities and getLockers return db-backed collections', function () {
    Country::factory()->create(['iso2' => 'RO']);

    $county = ShippingCounty::create([
        'provider' => 'sameday',
        'provider_county_id' => 10,
        'name' => 'Cluj',
        'code' => 'CJ',
    ]);

    $city = ShippingCity::create([
        'provider' => 'sameday',
        'provider_city_id' => 100,
        'name' => 'Cluj-Napoca',
        'postal_code' => '400000',
        'county_id' => $county->id,
        'provider_county_id' => 10,
    ]);

    ShippingLocker::create([
        'provider' => 'sameday',
        'provider_locker_id' => 12345,
        'name' => 'Locker 1',
        'locker_type' => 'easybox',
        'county' => 'Cluj',
        'county_id' => $county->id,
        'provider_county_id' => 10,
        'city' => 'Cluj-Napoca',
        'city_id' => $city->id,
        'provider_city_id' => 100,
        'postal_code' => '400000',
        'address' => 'Str. Memorandumului 1',
        'lat' => '46.7712',
        'lng' => '23.6236',
    ]);

    $client = \Mockery::mock(TokenAwareShippingApiClient::class);
    $provider = new SamedayShippingProvider($client);

    $counties = $provider->getCounties();
    $cities = $provider->getCities($county->id);
    $lockers = $provider->getLockers($county->id, $city->id);

    expect($counties)->toHaveCount(1)
        ->and($counties->first()->name)->toBe('Cluj')
        ->and($cities)->toHaveCount(1)
        ->and($cities->first()->name)->toBe('Cluj-Napoca')
        ->and($lockers)->toHaveCount(1)
        ->and($lockers->first()->id)->toBe(12345)
        ->and($lockers->first()->name)->toBe('Locker 1')
        ->and($lockers->first()->postalCode)->toBe('400000');
});

it('generateAWB wraps client exceptions into FailedAWBGenerationException', function () {
    $client = \Mockery::mock(TokenAwareShippingApiClient::class);
    $client->shouldReceive('generateAWB')
        ->once()
        ->with(\Mockery::type(AWBRequestBodyInterface::class))
        ->andThrow(new Exception('Exception'));

    $provider = new SamedayShippingProvider($client);

    $order = new Order;
    $order->reference = 'ORD-EX';
    $order->meta = ['payment_type' => 'online', 'shippingType' => ShippingType::COURIER->value];
    $order->setRelation('productLines', collect());
    $order->setRelation('shippingAddress', new OrderAddress([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'city' => 'Arad',
        'state' => 'Arad',
        'postcode' => '310000',
        'contact_phone' => '+40700000000',
        'contact_email' => 'john@example.com',
        'line_one' => 'Street 1',
    ]));

    $this->expectException(FailedAWBGenerationException::class);
    $provider->generateAWB($order);
});
