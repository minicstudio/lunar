<?php

uses(\Lunar\Tests\ERP\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Exceptions\FailedErpInvoiceGenerationException;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillClient;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillInvoiceRequestBody;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillPrintRequestQuery;
use Lunar\ERP\Providers\Smartbill\Requests\DownloadInvoicePDFRequest;
use Lunar\ERP\Providers\Smartbill\Requests\GenerateInvoiceRequest;
use Lunar\ERP\Providers\Smartbill\SmartbillApiClient;
use Lunar\Models\Order;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Config::set('lunar.erp.smartbill.base_url', 'https://smartbill.test');
    Config::set('lunar.erp.smartbill.email', 'user@test');
    Config::set('lunar.erp.smartbill.token', 'tok');
});

function makeSmartbillInvoiceBody(): SmartbillInvoiceRequestBody
{
    $client = new SmartbillClient('ACME', 'RO123', false, 'Addr', 'Cluj', 'Cluj', 'Romania', 'a@b.c', false);

    return new SmartbillInvoiceRequestBody('RO123', 'S', $client, []);
}

it('generateInvoice calls API and returns JSON', function () {
    $mock = new MockClient([
        GenerateInvoiceRequest::class => MockResponse::make(['series' => 'S', 'number' => 100], 200),
    ]);

    $client = new SmartbillApiClient;
    $client->withMockClient($mock);

    $resp = $client->generateInvoice(makeSmartbillInvoiceBody());
    expect($resp)->toBe(['series' => 'S', 'number' => 100]);
});

it('downloadInvoicePDF calls API and returns response', function () {
    $mock = new MockClient([
        DownloadInvoicePDFRequest::class => MockResponse::make('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $client = new SmartbillApiClient;
    $client->withMockClient($mock);

    $resp = $client->downloadInvoicePDF(new SmartbillPrintRequestQuery('S', '1', 'RO123'));
    expect($resp->successful())->toBeTrue();
});

it('generateInvoice throws on non-successful response', function () {
    $mock = new MockClient([
        GenerateInvoiceRequest::class => MockResponse::make('Bad', 400),
    ]);

    $client = new SmartbillApiClient;
    $client->withMockClient($mock);

    expect(fn() => $client->generateInvoice(makeSmartbillInvoiceBody()))
        ->toThrow(FailedErpInvoiceGenerationException::class);
});

it('downloadInvoicePDF throws on non-successful response', function () {
    $mock = new MockClient([
        DownloadInvoicePDFRequest::class => MockResponse::make('Bad', 400),
    ]);

    $client = new SmartbillApiClient;
    $client->withMockClient($mock);

    expect(fn() => $client->downloadInvoicePDF(new SmartbillPrintRequestQuery('S', '1', 'RO123')))
        ->toThrow(FailedErpInvoiceGenerationException::class);
});

it('getStock returns empty array', function () {
    $client = new SmartbillApiClient;
    expect($client->getStock())->toBe([]);
});

it('sendOrder returns empty array', function () {
    $client = new SmartbillApiClient;
    $order = new Order;
    expect($client->sendOrder($order))->toBe([]);
});

it('getProductList returns empty array', function () {
    $client = new SmartbillApiClient;
    expect($client->getProductList())->toBe([]);
});

it('confirmReceivingData returns empty array', function () {
    $client = new SmartbillApiClient;
    expect($client->confirmReceivingData(1, 1))->toBe([]);
});

it('getModifiedOrders returns empty array', function () {
    $client = new SmartbillApiClient;
    expect($client->getModifiedOrders())->toBe([]);
});
