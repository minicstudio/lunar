<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\ERP\Events\SplitOfflineOrderReadyForErp;
use Lunar\ERP\Listeners\SendOrderToERP;
use Lunar\ERP\Listeners\SendSplitOrdersToERP;
use Lunar\ERP\Services\ErpService;
use Lunar\Events\OrdersSplit;
use Lunar\Models\Order;
use Lunar\Tests\ERP\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createCurrencies();
});

it('queues the placement ERP listener for offline split orders only', function () {
    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.queue.name', 'erp');
    Config::set('lunar.erp.queue.connection', 'database');
    Event::fake([OrderPlacedEvent::class]);
    Queue::fake();

    $original = Order::factory()->create([
        'meta' => ['payment_option' => 'cash-on-delivery'],
    ]);
    $offline = Order::factory()->create([
        'status' => 'payment-offline',
        'meta' => ['payment_option' => 'cash-on-delivery'],
    ]);
    $cashInHand = Order::factory()->create([
        'status' => 'payment-offline',
        'meta' => ['payment_option' => 'cash-in-hand'],
    ]);
    $card = Order::factory()->create([
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    $service = Mockery::mock(ErpService::class);
    $service->shouldReceive('getEnabledProviders')->once()->andReturn([ErpProviderEnum::magister]);
    $service->shouldNotReceive('sendOrder');
    app()->instance(ErpService::class, $service);

    (new SendSplitOrdersToERP)->handle(new OrdersSplit([$offline, $cashInHand, $card]));

    $queued = Queue::listenersPushed(SendOrderToERP::class);

    expect($queued)->toHaveCount(2)
        ->and($queued->every(fn ($job) => $job->tries === 3 && $job->backoff === [60, 300, 900]))->toBeTrue();

    $queuedOrders = $queued
        ->map(fn ($job) => $job->data[0] ?? null)
        ->filter(fn ($event) => $event instanceof SplitOfflineOrderReadyForErp)
        ->map(fn (SplitOfflineOrderReadyForErp $event) => $event->order->id)
        ->all();

    expect($queuedOrders)->toEqualCanonicalizing([$offline->id, $cashInHand->id])
        ->and(Queue::listenersPushed(SendOrderToERP::class, fn ($event, $job, $queue) => $queue === 'erp'))->toHaveCount(2);

    $listeners = collect(app('events')->getRawListeners()[OrdersSplit::class] ?? [])
        ->flatten()
        ->filter(fn (mixed $listener): bool => is_string($listener))
        ->all();
    $placement = (new ReflectionMethod(SendOrderToERP::class, 'handle'))->getParameters()[0]->getType();
    $placementNames = $placement instanceof ReflectionUnionType
        ? array_map(fn (ReflectionNamedType $type): string => $type->getName(), $placement->getTypes())
        : [$placement instanceof ReflectionNamedType ? $placement->getName() : null];

    expect($listeners)->toContain(SendSplitOrdersToERP::class)
        ->and(app('events')->getRawListeners()[SplitOfflineOrderReadyForErp::class] ?? [])->toContain(SendOrderToERP::class)
        ->and($placementNames)->toContain(OrderPlacedEvent::class)
        ->and(Order::query()->find($original->id))->not->toBeNull();

    Event::assertNotDispatched(OrderPlacedEvent::class);
});

it('moves an offline split order to failed-erp-sync when the send fails and still sends the other order', function () {
    Config::set('lunar.erp.enabled', true);
    Config::set('queue.default', 'sync');

    $failing = Order::factory()->create([
        'status' => 'payment-offline',
        'meta' => ['payment_option' => 'cash-on-delivery'],
    ]);
    $other = Order::factory()->create([
        'status' => 'payment-offline',
        'meta' => ['payment_option' => 'offline'],
    ]);

    $service = Mockery::mock(ErpService::class);
    $service->shouldReceive('getEnabledProviders')->andReturn([ErpProviderEnum::magister]);
    $service->shouldReceive('getAllowedProviders')->andReturn([]);
    $service->shouldReceive('sendOrder')
        ->once()
        ->with(ErpProviderEnum::magister, Mockery::on(fn ($order) => $order->is($failing)))
        ->andThrow(new RuntimeException('ERP unreachable'));
    $service->shouldReceive('sendOrder')
        ->once()
        ->with(ErpProviderEnum::magister, Mockery::on(fn ($order) => $order->is($other)))
        ->andReturnTrue();
    app()->instance(ErpService::class, $service);

    (new SendSplitOrdersToERP)->handle(new OrdersSplit([$failing, $other]));

    $failing->refresh();

    expect($failing->status)->toBe('failed-erp-sync')
        ->and($failing->meta['status_before_erp_failure'])->toBe('payment-offline')
        ->and(Order::query()->find($failing->id))->not->toBeNull()
        ->and(Order::query()->find($other->id))->not->toBeNull();
});
