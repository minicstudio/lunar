<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Filament\Actions\ResendOrderToErpAction;
use Lunar\ERP\Services\ErpService;
use Lunar\Locations\Models\County;
use Lunar\Locations\Models\Locality;
use Lunar\Models\Country;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

/**
 * Creates a Locality row scoped to the given county name.
 */
function seedResendTestLocality(string $name, string $county = 'County'): Locality
{
    $countyModel = County::firstOrCreate(
        ['name' => $county],
        ['code' => strtoupper(substr($county, 0, 3)), 'country_id' => Country::factory()->create()->id]
    );

    return Locality::create(['name' => $name, 'county_id' => $countyModel->id]);
}

beforeEach(function () {
    $this->createCurrencies();
});

function callResendOrderToErpHandle(Order $order): void
{
    $method = new ReflectionMethod(ResendOrderToErpAction::class, 'handle');
    $method->setAccessible(true);
    $method->invoke(null, $order);
}

it('resolves the action label and notifications from the lunarpanel.erp translation namespace', function () {
    app()->setLocale('en');
    expect(__('lunarpanel.erp::actions.resend_to_erp.label'))->toBe('Resend to ERP');
    expect(__('lunarpanel.erp::actions.resend_to_erp.notification.success'))->toBe('Order successfully resent to the ERP.');

    app()->setLocale('hu');
    expect(__('lunarpanel.erp::actions.resend_to_erp.label'))->toBe('Újraküldés az ERP-nek');

    app()->setLocale('ro');
    expect(__('lunarpanel.erp::actions.resend_to_erp.label'))->toBe('Retrimite către ERP');
});

it('keeps the order flagged failed-erp-sync when it has no shipping address at all', function () {
    Config::set('lunar.erp.enabled', true);

    seedResendTestLocality('Cluj-Napoca');

    $order = Order::factory()->create([
        'status' => 'failed-erp-sync',
        'meta' => ['status_before_erp_failure' => 'awaiting-payment'],
    ]);

    $service = \Mockery::mock(ErpService::class);
    $service->shouldNotReceive('getEnabledProviders');
    $service->shouldNotReceive('sendOrder');
    $service->shouldReceive('getAllowedProviders')->andReturn([]);
    app()->instance(ErpService::class, $service);

    callResendOrderToErpHandle($order);

    $order->refresh();
    expect($order->status)->toBe('failed-erp-sync');
});

it('restores the pre-failure status and clears the stashed meta on a successful resend', function () {
    Config::set('lunar.erp.enabled', true);

    $order = Order::factory()->create([
        'status' => 'failed-erp-sync',
        'meta' => ['status_before_erp_failure' => 'awaiting-payment'],
    ]);

    $service = \Mockery::mock(ErpService::class);
    $service->shouldReceive('getEnabledProviders')->once()->andReturn([ErpProviderEnum::magister]);
    $service->shouldReceive('sendOrder')->once()->andReturnTrue();
    $service->shouldReceive('getAllowedProviders')->andReturn([]);
    app()->instance(ErpService::class, $service);

    callResendOrderToErpHandle($order);

    $order->refresh();
    expect($order->status)->toBe('awaiting-payment')
        ->and(isset($order->meta['status_before_erp_failure']))->toBeFalse()
        ->and($order->meta->getArrayCopy())->toBe([]);
});

it('re-sets failed-erp-sync when the resend attempt fails again', function () {
    Config::set('lunar.erp.enabled', true);

    $order = Order::factory()->create([
        'status' => 'failed-erp-sync',
        'meta' => ['status_before_erp_failure' => 'awaiting-payment'],
    ]);

    $service = \Mockery::mock(ErpService::class);
    $service->shouldReceive('getEnabledProviders')->once()->andReturn([ErpProviderEnum::magister]);
    $service->shouldReceive('sendOrder')->once()->andReturnFalse();
    app()->instance(ErpService::class, $service);

    callResendOrderToErpHandle($order);

    $order->refresh();
    expect($order->status)->toBe('failed-erp-sync');
});

it('keeps the order flagged invalid-address then failed-erp-sync when the address is still wrong', function () {
    Config::set('lunar.erp.enabled', true);

    seedResendTestLocality('Cluj-Napoca');

    $order = Order::factory()
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'city' => 'Still Wrong',
        ]), 'shippingAddress')
        ->create([
            'status' => 'failed-erp-sync',
            'meta' => ['status_before_erp_failure' => 'awaiting-payment'],
        ]);

    $service = \Mockery::mock(ErpService::class);
    $service->shouldNotReceive('getEnabledProviders');
    $service->shouldNotReceive('sendOrder');
    $service->shouldReceive('getAllowedProviders')->andReturn([]);
    app()->instance(ErpService::class, $service);

    callResendOrderToErpHandle($order);

    $order->refresh();
    expect($order->status)->toBe('failed-erp-sync');
});

it('keeps the order flagged invalid-address then failed-erp-sync when the city matches but the county does not', function () {
    Config::set('lunar.erp.enabled', true);

    seedResendTestLocality('Cluj-Napoca', 'Cluj');

    $order = Order::factory()
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'city' => 'Cluj-Napoca',
            'state' => 'Bihor',
        ]), 'shippingAddress')
        ->create([
            'status' => 'failed-erp-sync',
            'meta' => ['status_before_erp_failure' => 'awaiting-payment'],
        ]);

    $service = \Mockery::mock(ErpService::class);
    $service->shouldNotReceive('getEnabledProviders');
    $service->shouldNotReceive('sendOrder');
    $service->shouldReceive('getAllowedProviders')->andReturn([]);
    app()->instance(ErpService::class, $service);

    callResendOrderToErpHandle($order);

    $order->refresh();
    expect($order->status)->toBe('failed-erp-sync');
});

it('resends when the city and county both match a seeded locality', function () {
    Config::set('lunar.erp.enabled', true);

    seedResendTestLocality('Cluj-Napoca', 'Cluj');

    $order = Order::factory()
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'city' => 'Cluj-Napoca',
            'state' => 'Cluj',
        ]), 'shippingAddress')
        ->create([
            'status' => 'failed-erp-sync',
            'meta' => ['status_before_erp_failure' => 'awaiting-payment'],
        ]);

    $service = \Mockery::mock(ErpService::class);
    $service->shouldReceive('getEnabledProviders')->once()->andReturn([ErpProviderEnum::magister]);
    $service->shouldReceive('sendOrder')->once()->andReturnTrue();
    $service->shouldReceive('getAllowedProviders')->andReturn([]);
    app()->instance(ErpService::class, $service);

    callResendOrderToErpHandle($order);

    $order->refresh();
    expect($order->status)->toBe('awaiting-payment');
});
