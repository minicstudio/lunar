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
