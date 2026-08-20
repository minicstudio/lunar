<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\ERP\Listeners\SendOrderToERP;
use Lunar\ERP\Services\ErpService;
use Lunar\ERP\Support\OrderStatusUpdater;
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
            ->with($provider, \Mockery::on(fn ($o) => $o->is($order)))
            ->andReturnTrue();
    }
    app()->instance(ErpService::class, $service);

    (new SendOrderToERP)->handle(new OrderPlacedEvent($order));
});

test('the listener retries a limited number of times with backoff', function () {
    $listener = new SendOrderToERP;

    expect($listener->tries)->toBe(3)
        ->and($listener->backoff)->toBe([60, 300, 900]);
});

test('the listener routes to the configured ERP queue and connection', function () {
    Config::set('lunar.erp.queue.name', 'erp');
    Config::set('lunar.erp.queue.connection', 'database');

    $listener = new SendOrderToERP;

    expect($listener->viaQueue())->toBe('erp')
        ->and($listener->viaConnection())->toBe('database');
});

it('marks the order as failed-erp-sync once all attempts are exhausted', function () {
    $order = Order::factory()->create();

    (new SendOrderToERP)->failed(new OrderPlacedEvent($order), new Exception('ERP unreachable'));

    $order->refresh();
    expect($order->status)->toBe('failed-erp-sync')
        ->and($order->meta['status_before_erp_failure'])->toBe('awaiting-payment')
        // Regression guard: meta must stay flat (no Collection object stored in place of an array).
        ->and(collect($order->meta->getArrayCopy())->every(fn ($value) => ! is_array($value)))->toBeTrue();
});

it('stashes the status from before the order was flagged invalid-address, not invalid-address itself', function () {
    activity()->enableLogging();

    $order = Order::factory()->create();

    (new OrderStatusUpdater)->handle($order, [
        'status' => 'invalid-address',
    ]);

    (new SendOrderToERP)->failed(new OrderPlacedEvent($order), new Exception('ERP unreachable'));

    $order->refresh();
    expect($order->status)->toBe('failed-erp-sync')
        ->and($order->meta['status_before_erp_failure'])->toBe('awaiting-payment');

    activity()->disableLogging();
});

it('stashes the status from before the most recent invalid-address/failed-erp-sync chain when the order bounced between statuses more than once', function () {
    activity()->enableLogging();

    $order = Order::factory()->create();

    // awaiting-payment -> invalid-address -> payment-received -> invalid-address
    (new OrderStatusUpdater)->handle($order, ['status' => 'invalid-address']);
    (new OrderStatusUpdater)->handle($order, ['status' => 'payment-received']);
    (new OrderStatusUpdater)->handle($order, ['status' => 'invalid-address']);

    (new SendOrderToERP)->failed(new OrderPlacedEvent($order), new Exception('ERP unreachable'));

    $order->refresh();
    expect($order->status)->toBe('failed-erp-sync')
        ->and($order->meta['status_before_erp_failure'])->toBe('payment-received');

    activity()->disableLogging();
});
