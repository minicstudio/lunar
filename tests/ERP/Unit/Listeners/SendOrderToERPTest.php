<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\ERP\Listeners\SendOrderToERP;
use Lunar\ERP\Services\ErpService;
use Lunar\Models\Order;

beforeEach(function () {
    $this->createCurrencies();
});

test('listener implements ShouldQueue', function () {
    expect(new SendOrderToERP)->toBeInstanceOf(ShouldQueue::class);
});

it('returns early when ERP is disabled', function () {
    Config::set('lunar.erp.enabled', false);

    $service = \Mockery::mock(ErpService::class);
    $service->shouldNotReceive('getEnabledProviders');
    $service->shouldNotReceive('sendOrder');
    app()->instance(ErpService::class, $service);

    $order = Order::factory()->create();
    (new SendOrderToERP)->handle(new OrderPlacedEvent($order));
});

it('does not send when there are no enabled providers', function () {
    Config::set('lunar.erp.enabled', true);

    $service = \Mockery::mock(ErpService::class);
    $service->shouldReceive('getEnabledProviders')->once()->andReturn([]);
    $service->shouldNotReceive('sendOrder');
    app()->instance(ErpService::class, $service);

    $order = Order::factory()->create();
    (new SendOrderToERP)->handle(new OrderPlacedEvent($order));
});

it('sends the order to each enabled provider', function () {
    Config::set('lunar.erp.enabled', true);

    $providers = [ErpProviderEnum::magister, ErpProviderEnum::smartbill];
    $order = Order::factory()->create();

    $service = \Mockery::mock(ErpService::class);
    $service->shouldReceive('getEnabledProviders')->once()->andReturn($providers);
    foreach ($providers as $provider) {
        $service->shouldReceive('sendOrder')
            ->once()
            ->with($provider, \Mockery::on(fn($o) => $o->is($order)))
            ->andReturnTrue();
    }
    app()->instance(ErpService::class, $service);

    (new SendOrderToERP)->handle(new OrderPlacedEvent($order));
});
