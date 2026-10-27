<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\ERP\Listeners\SendOrderToERP;
use Lunar\ERP\Services\ErpService;
use Lunar\ERP\Support\OrderStatusUpdater;
use Lunar\Models\Order;
use Lunar\Tests\ERP\Stubs\FakeErpClient;
use Lunar\Tests\ERP\Stubs\FakeOrderExporter;
use Lunar\Tests\ERP\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createCurrencies();
});

test('listener implements ShouldQueue', function () {
    expect(new SendOrderToERP)->toBeInstanceOf(ShouldQueue::class);
});

it('returns early when ERP is disabled', function () {
    Config::set('lunar.erp.enabled', false);

    $service = Mockery::mock(ErpService::class);
    $service->shouldNotReceive('getEnabledProviders');
    $service->shouldNotReceive('sendOrder');
    app()->instance(ErpService::class, $service);

    $order = Order::factory()->create();
    (new SendOrderToERP)->handle(new OrderPlacedEvent($order));
});

it('does not send when there are no enabled providers', function () {
    Config::set('lunar.erp.enabled', true);

    $service = Mockery::mock(ErpService::class);
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

    $service = Mockery::mock(ErpService::class);
    $service->shouldReceive('getEnabledProviders')->once()->andReturn($providers);
    foreach ($providers as $provider) {
        $service->shouldReceive('sendOrder')
            ->once()
            ->with($provider, Mockery::on(fn ($o) => $o->is($order)))
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

function configureFakeOrderExporter(bool $accepts, bool $throws = false): void
{
    FakeOrderExporter::$accepts = $accepts;
    FakeOrderExporter::$throws = $throws;
    FakeOrderExporter::$sentMeta = null;

    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['magister']);
    Config::set('lunar.erp.magister.enabled', true);
    Config::set('lunar.erp.magister.exporter_class', FakeOrderExporter::class);
    Config::set('lunar.erp.magister.client_class', FakeErpClient::class);
    Config::set('lunar.erp.actions.send_order', ['magister']);
}

it('stores erp_sent_at when the provider accepts the order and keeps the status', function () {
    configureFakeOrderExporter(true);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
    ]);

    $sent = app(ErpService::class)->sendOrder(ErpProviderEnum::magister, $order);

    $order->refresh();

    expect($sent)->toBeTrue()
        ->and($order->status)->toBe('awaiting-payment')
        ->and($order->meta['erp_sent_at'])->not->toBeEmpty();
});

it('records the timestamp on the current order after the provider accepts', function () {
    configureFakeOrderExporter(true);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
        'meta' => ['payment_reference' => 'pay-1'],
    ]);
    $stale = Order::query()->findOrFail($order->id);

    $order->update([
        'meta' => [
            'payment_reference' => 'pay-1',
            'loyalty_points_to_redeem' => 40,
        ],
    ]);

    $sent = app(ErpService::class)->sendOrder(ErpProviderEnum::magister, $stale);

    $order->refresh();

    expect($sent)->toBeTrue()
        ->and(isset(FakeOrderExporter::$sentMeta['erp_sent_at']))->toBeFalse()
        ->and($order->status)->toBe('awaiting-payment')
        ->and($order->meta['payment_reference'])->toBe('pay-1')
        ->and($order->meta['loyalty_points_to_redeem'])->toBe(40)
        ->and($order->meta['erp_sent_at'])->not->toBeEmpty();
});

it('returns success when the provider accepts and saving the timestamp fails', function () {
    configureFakeOrderExporter(true);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
        'meta' => ['payment_reference' => 'pay-1'],
    ]);

    $service = new class extends ErpService
    {
        protected function stampSentAt(Order $order): void
        {
            throw new RuntimeException('meta save failed');
        }
    };

    $sent = $service->sendOrder(ErpProviderEnum::magister, $order);

    $order->refresh();

    expect($sent)->toBeTrue()
        ->and(FakeOrderExporter::$sentMeta['payment_reference'])->toBe('pay-1')
        ->and(isset($order->meta['erp_sent_at']))->toBeFalse()
        ->and($order->status)->toBe('awaiting-payment');
});

it('leaves erp_sent_at empty when the provider rejects the order', function () {
    configureFakeOrderExporter(false);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
    ]);

    $sent = app(ErpService::class)->sendOrder(ErpProviderEnum::magister, $order);

    $order->refresh();

    expect($sent)->toBeFalse()
        ->and(isset($order->meta['erp_sent_at']))->toBeFalse();
});

it('leaves erp_sent_at empty when the provider throws', function () {
    configureFakeOrderExporter(true, true);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
    ]);

    expect(fn () => app(ErpService::class)->sendOrder(ErpProviderEnum::magister, $order))
        ->toThrow(RuntimeException::class);

    $order->refresh();

    expect(isset($order->meta['erp_sent_at']))->toBeFalse();
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
