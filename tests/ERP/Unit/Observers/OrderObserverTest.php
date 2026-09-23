<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Exceptions;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Exceptions\FailedErpInvoiceGenerationException;
use Lunar\ERP\Services\ErpService;
use Lunar\Models\Order;
use Lunar\Tests\ERP\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createCurrencies();

    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.smartbill.enabled', true);
    Config::set('lunar.erp.smartbill.generate_invoice', ['awaiting-payment']);
});

it('still saves the order status when invoice generation fails', function () {
    Exceptions::fake();

    $order = Order::factory()->create(['status' => 'payment-received']);

    $service = Mockery::mock(ErpService::class);
    $service->shouldReceive('getAllowedProviders')->with('actions', 'billing')->andReturn([ErpProviderEnum::smartbill]);
    $service->shouldReceive('generateInvoice')
        ->once()
        ->andThrow(new FailedErpInvoiceGenerationException('Invoice generation failed: SmartBill unavailable'));
    app()->instance(ErpService::class, $service);

    $order->update(['status' => 'awaiting-payment']);

    $order->refresh();
    expect($order->status)->toBe('awaiting-payment')
        ->and($order->meta['billing_series'] ?? null)->toBeNull()
        ->and($order->meta['billing_number'] ?? null)->toBeNull();

    Exceptions::assertReported(FailedErpInvoiceGenerationException::class);
});

it('generates the invoice when the order enters a trigger status', function () {
    $order = Order::factory()->create(['status' => 'payment-received']);

    $service = Mockery::mock(ErpService::class);
    $service->shouldReceive('getAllowedProviders')->with('actions', 'billing')->andReturn([ErpProviderEnum::smartbill]);
    $service->shouldReceive('generateInvoice')
        ->once()
        ->with(ErpProviderEnum::smartbill, Mockery::on(fn ($o) => $o->is($order)));
    app()->instance(ErpService::class, $service);

    $order->update(['status' => 'awaiting-payment']);
});
