<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Events\OrderInvoiceGenerated;
use Lunar\ERP\Services\ErpService;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Lunar\Tests\ERP\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createCurrencies();

    Config::set('lunar.erp.enabled', false);
    Config::set('lunar.erp.smartbill.enabled', false);
    Config::set('lunar.erp.actions.billing', []);
    Config::set('lunar.invoicing.statuses_by_payment_option', [
        'stripe-card' => 'payment-received',
        'stripe-wallet-pay' => 'payment-received',
        'bank-transfer' => 'awaiting-bank-transfer',
        'cash-on-delivery' => 'payment-offline',
    ]);
});

it('marks lunar invoice generated and dispatches event on configured status', function () {
    Event::fake([OrderInvoiceGenerated::class]);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    $order->update(['status' => 'payment-received']);

    $order->refresh();

    expect($order->meta['lunar_invoice_generated_at'] ?? null)->not->toBeNull();

    Event::assertDispatched(OrderInvoiceGenerated::class, function (OrderInvoiceGenerated $event) use ($order) {
        return $event->order->is($order) && $event->source === 'lunar';
    });
});

it('always generates a lunar invoice when the order reaches completed', function () {
    Event::fake([OrderInvoiceGenerated::class]);

    $order = Order::factory()->create([
        'status' => 'payment-received',
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    $order->update(['status' => 'completed']);

    $order->refresh();

    expect($order->meta['lunar_invoice_generated_at'] ?? null)->not->toBeNull();

    Event::assertDispatched(OrderInvoiceGenerated::class, function (OrderInvoiceGenerated $event) use ($order) {
        return $event->order->is($order) && $event->source === 'lunar';
    });
});

it('generates a lunar invoice for bank transfer on awaiting-bank-transfer', function () {
    Event::fake([OrderInvoiceGenerated::class]);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
        'meta' => ['payment_option' => 'bank-transfer'],
    ]);

    $order->update(['status' => 'awaiting-bank-transfer']);

    $order->refresh();

    expect($order->meta['lunar_invoice_generated_at'] ?? null)->not->toBeNull();

    Event::assertDispatched(OrderInvoiceGenerated::class, function (OrderInvoiceGenerated $event) use ($order) {
        return $event->order->is($order) && $event->source === 'lunar';
    });
});

it('skips lunar invoicing when erp billing is active', function () {
    Event::fake([OrderInvoiceGenerated::class]);

    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['smartbill']);
    Config::set('lunar.erp.smartbill.enabled', true);
    Config::set('lunar.erp.actions.billing', ['smartbill']);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    $order->update(['status' => 'payment-received']);

    $order->refresh();

    expect($order->meta['lunar_invoice_generated_at'] ?? null)->toBeNull();
    Event::assertNotDispatched(OrderInvoiceGenerated::class);
});

it('issues a lunar invoice for bank transfer when erp billing leaves that status out', function () {
    Event::fake([OrderInvoiceGenerated::class]);

    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['smartbill']);
    Config::set('lunar.erp.smartbill.enabled', true);
    Config::set('lunar.erp.smartbill.generate_invoice', ['payment-received']);
    Config::set('lunar.erp.actions.billing', ['smartbill']);

    $order = Order::factory()->create([
        'status' => 'awaiting-phone-consultation',
        'meta' => ['payment_option' => 'bank-transfer'],
    ]);

    $order->update(['status' => 'awaiting-bank-transfer']);

    $order->refresh();

    expect($order->meta['lunar_invoice_generated_at'] ?? null)->not->toBeNull();

    Event::assertDispatched(OrderInvoiceGenerated::class, function (OrderInvoiceGenerated $event) use ($order) {
        return $event->order->is($order) && $event->source === 'lunar';
    });
});

it('leaves bank transfer to erp when that status is an erp invoice trigger', function () {
    Event::fake([OrderInvoiceGenerated::class]);

    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['smartbill']);
    Config::set('lunar.erp.smartbill.enabled', true);
    Config::set('lunar.erp.smartbill.generate_invoice', ['awaiting-bank-transfer']);
    Config::set('lunar.erp.actions.billing', ['smartbill']);

    $service = Mockery::mock(ErpService::class);
    $service->shouldReceive('getAllowedProviders')->with('actions', 'billing')->andReturn([ErpProviderEnum::smartbill]);
    $service->shouldReceive('generateInvoice')->once();
    app()->instance(ErpService::class, $service);

    $order = Order::factory()->create([
        'status' => 'awaiting-phone-consultation',
        'meta' => ['payment_option' => 'bank-transfer'],
    ]);

    $order->update(['status' => 'awaiting-bank-transfer']);

    $order->refresh();

    expect($order->meta['lunar_invoice_generated_at'] ?? null)->toBeNull();

    Event::assertDispatched(OrderInvoiceGenerated::class, function (OrderInvoiceGenerated $event) use ($order) {
        return $event->order->is($order) && $event->source === 'smartbill';
    });
});

it('does not invoice a bank transfer while preorder consultation is still open', function () {
    Event::fake([OrderInvoiceGenerated::class]);
    $this->createLanguages();

    $order = Order::factory()->create([
        'status' => 'awaiting-phone-consultation',
        'meta' => ['payment_option' => 'bank-transfer'],
    ]);
    $variant = ProductVariant::factory()->create();

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'meta' => ['preorder' => true],
    ]);

    $order->update(['status' => 'awaiting-bank-transfer']);

    expect($order->fresh()->meta['lunar_invoice_generated_at'] ?? null)->toBeNull();
    Event::assertNotDispatched(OrderInvoiceGenerated::class);
});

it('does not re-dispatch when lunar invoice was already generated', function () {
    Event::fake([OrderInvoiceGenerated::class]);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
        'meta' => [
            'payment_option' => 'stripe-card',
            'lunar_invoice_generated_at' => now()->toIso8601String(),
        ],
    ]);

    $order->update(['status' => 'payment-received']);

    Event::assertNotDispatched(OrderInvoiceGenerated::class);
});
