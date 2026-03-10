<?php

uses(\Lunar\Tests\ERP\TestCase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Providers\Smartbill\Requests\DownloadInvoicePDFRequest;
use Lunar\ERP\Providers\Smartbill\Requests\GenerateInvoiceRequest;
use Saloon\Enums\Method;

beforeEach(function () {
    Config::set('lunar.erp.smartbill.email', 'user@test');
    Config::set('lunar.erp.smartbill.token', 'tok');
});

it('GenerateInvoiceRequest builds endpoint, headers, method and body', function () {
    $payload = ['foo' => 'bar'];
    $req = new GenerateInvoiceRequest($payload);

    expect($req->resolveEndpoint())->toBe('/invoice')
        ->and($req->defaultHeaders())->toMatchArray([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->and($req->defaultBody())->toBe($payload);

    $ref = new ReflectionClass($req);
    $prop = $ref->getProperty('method');
    $prop->setAccessible(true);
    expect($prop->getValue($req))->toBe(Method::POST);
});

it('DownloadInvoicePDFRequest builds endpoint, headers, method and query', function () {
    $payload = ['seriesname' => 'S', 'number' => '1', 'cif' => 'RO123'];
    $req = new DownloadInvoicePDFRequest($payload);

    expect($req->resolveEndpoint())->toBe('/invoice/pdf')
        ->and($req->defaultHeaders())->toMatchArray([
            'Content-Type' => 'application/json',
            'Accept' => 'application/octet-stream',
        ])
        ->and($req->defaultQuery())->toBe($payload);

    $ref = new ReflectionClass($req);
    $prop = $ref->getProperty('method');
    $prop->setAccessible(true);
    expect($prop->getValue($req))->toBe(Method::GET);
});
