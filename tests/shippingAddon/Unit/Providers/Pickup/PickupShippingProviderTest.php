<?php

uses(\Lunar\Tests\ShippingAddon\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lunar\Addons\Shipping\Contracts\ShippingApiClient;
use Lunar\Addons\Shipping\Providers\Pickup\PickupApiClient;
use Lunar\Addons\Shipping\Providers\Pickup\PickupShippingProvider;
use Lunar\Models\Currency;
use Lunar\Models\Order;

beforeEach(function () {
    $this->createLanguages();
    config(['lunar.shipping.pickup.enabled' => true]);

    if (! Schema::hasTable('lunar_shipping_methods')) {
        Schema::create('lunar_shipping_methods', function ($table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    DB::table('lunar_shipping_methods')->updateOrInsert(
        ['code' => 'pickup'],
        [
            'name' => 'Personal pickup',
            'description' => 'Test Company Address, Street 123',
            'updated_at' => now(),
            'created_at' => now(),
        ]
    );
});

test('creates provider with api client', function () {
    $client = new PickupApiClient;
    $provider = new PickupShippingProvider($client);

    expect($provider)->toBeInstanceOf(PickupShippingProvider::class);
});

test('isEnabled returns true when config is enabled', function () {
    config(['lunar.shipping.pickup.enabled' => true]);

    $client = new PickupApiClient;
    $provider = new PickupShippingProvider($client);

    expect($provider->isEnabled())->toBeTrue();
});

test('getName returns translated personal pickup string', function () {
    $client = new PickupApiClient;
    $provider = new PickupShippingProvider($client);

    $name = $provider->getName();

    expect($name)->toBeString();
    expect($name)->toContain('Personal pickup');
});

test('getDescription returns company address from config', function () {
    $client = new PickupApiClient;
    $provider = new PickupShippingProvider($client);

    $description = $provider->getDescription();

    expect($description)->toBe('Test Company Address, Street 123');
});

test('generateAWB calls client generateAWB with null', function () {
    $mockClient = Mockery::mock(ShippingApiClient::class);
    $mockClient->shouldReceive('generateAWB')
        ->once()
        ->with(null)
        ->andReturn(['awbNumber' => null]);

    $provider = new PickupShippingProvider($mockClient);

    $currency = Currency::factory()->create();
    $cart = $this->createCart($currency);
    $order = Order::factory()->create([
        'cart_id' => $cart->id,
    ]);

    $result = $provider->generateAWB($order);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('awbNumber');
    expect($result['awbNumber'])->toBeNull();
});

test('downloadAWBPDF calls client downloadAWBPDF', function () {
    $mockClient = Mockery::mock(ShippingApiClient::class);
    $mockClient->shouldReceive('downloadAWBPDF')
        ->once()
        ->with('test-awb')
        ->andReturn(null);

    $provider = new PickupShippingProvider($mockClient);

    $result = $provider->downloadAWBPDF('test-awb');

    expect($result)->toBeNull();
});

test('getLockers returns empty collection', function () {
    $client = new PickupApiClient;
    $provider = new PickupShippingProvider($client);

    $lockers = $provider->getLockers(1, 10);

    expect($lockers)->toBeInstanceOf(Collection::class);
    expect($lockers->count())->toBe(0);
    expect($lockers->isEmpty())->toBeTrue();
});

test('getCounties returns empty collection', function () {
    $client = new PickupApiClient;
    $provider = new PickupShippingProvider($client);

    $counties = $provider->getCounties();

    expect($counties)->toBeInstanceOf(Collection::class);
    expect($counties->count())->toBe(0);
    expect($counties->isEmpty())->toBeTrue();
});

test('getCities returns empty collection', function () {
    $client = new PickupApiClient;
    $provider = new PickupShippingProvider($client);

    $cities = $provider->getCities(1);

    expect($cities)->toBeInstanceOf(Collection::class);
    expect($cities->count())->toBe(0);
    expect($cities->isEmpty())->toBeTrue();
});
