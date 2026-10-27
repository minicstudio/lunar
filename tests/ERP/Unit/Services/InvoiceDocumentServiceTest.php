<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Lunar\ERP\Services\InvoiceDocumentService;
use Lunar\Models\Order;
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
        'bank-transfer' => 'awaiting-bank-transfer',
    ]);
});

it('treats completed orders as lunar invoice eligible', function () {
    $order = Order::factory()->create([
        'status' => 'completed',
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    expect(app(InvoiceDocumentService::class)->isLunarInvoiceEligible($order))->toBeTrue();
});

it('treats payment-option configured statuses as lunar invoice eligible', function () {
    $order = Order::factory()->create([
        'status' => 'payment-received',
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    expect(app(InvoiceDocumentService::class)->isLunarInvoiceEligible($order))->toBeTrue();
});

it('does not treat unrelated statuses as lunar invoice eligible', function () {
    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    expect(app(InvoiceDocumentService::class)->isLunarInvoiceEligible($order))->toBeFalse();
});

it('exposes customer invoice when lunar eligible without generation stamp', function () {
    $order = Order::factory()->create([
        'status' => 'completed',
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    expect(app(InvoiceDocumentService::class)->hasCustomerInvoice($order))->toBeTrue();
});

it('hides customer invoice when erp billing is active without billing meta', function () {
    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['smartbill']);
    Config::set('lunar.erp.smartbill.enabled', true);
    Config::set('lunar.erp.actions.billing', ['smartbill']);

    $order = Order::factory()->create([
        'status' => 'completed',
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    expect(app(InvoiceDocumentService::class)->hasCustomerInvoice($order))->toBeFalse();
});

it('exposes customer invoice when erp billing meta exists', function () {
    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['smartbill']);
    Config::set('lunar.erp.smartbill.enabled', true);
    Config::set('lunar.erp.actions.billing', ['smartbill']);

    $order = Order::factory()->create([
        'status' => 'awaiting-payment',
        'meta' => [
            'billing_series' => 'TEST',
            'billing_number' => '1',
        ],
    ]);

    expect(app(InvoiceDocumentService::class)->hasCustomerInvoice($order))->toBeTrue();
});

it('stores invoice stamps in panel date format', function () {
    Config::set('lunar.panel.timezone', 'Europe/Bucharest');
    Config::set('app.timezone', 'UTC');

    $order = Order::factory()->create([
        'status' => 'completed',
        'meta' => [],
    ]);

    $service = app(InvoiceDocumentService::class);
    $service->markLunarInvoiceGenerated($order);
    $service->markInvoiceEmailed($order);

    expect($order->meta['lunar_invoice_generated_at'])
        ->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2} (am|pm)$/')
        ->and($order->meta['invoice_emailed_at'])
        ->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2} (am|pm)$/')
        ->and($order->meta['lunar_invoice_generated_at'])
        ->toBe(now()->timezone('Europe/Bucharest')->format('Y-m-d h:i a'));
});
