<?php

use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillClient;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillInvoiceRequestBody;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillPrintRequestQuery;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillProduct;

it('serializes SmartbillClient DTO', function () {
    $dto = new SmartbillClient(
        name: 'ACME SRL',
        vatCode: 'RO123',
        isTaxPayer: false,
        address: 'Str. Test 1',
        city: 'Cluj',
        county: 'Cluj',
        country: 'Romania',
        email: 'a@b.c',
        saveToDb: false,
    );

    expect($dto->toArray())->toBe([
        'name' => 'ACME SRL',
        'vatCode' => 'RO123',
        'isTaxPayer' => false,
        'address' => 'Str. Test 1',
        'city' => 'Cluj',
        'county' => 'Cluj',
        'country' => 'Romania',
        'email' => 'a@b.c',
        'saveToDb' => false,
    ]);
});

it('serializes SmartbillProduct DTO', function () {
    $dto = new SmartbillProduct(
        name: 'Prod 1',
        code: 'P1',
        measuringUnitName: 'buc',
        currency: 'RON',
        quantity: 2,
        price: 12.5,
        isTaxIncluded: true,
        taxName: 'TVA',
        taxPercentage: 19.0,
        saveToDb: false,
        isService: false,
    );

    expect($dto->toArray())->toBe([
        'name' => 'Prod 1',
        'code' => 'P1',
        'measuringUnitName' => 'buc',
        'currency' => 'RON',
        'quantity' => 2.0,
        'price' => 12.5,
        'isTaxIncluded' => true,
        'taxName' => 'TVA',
        'taxPercentage' => 19.0,
        'isService' => false,
    ]);
});

it('serializes SmartbillPrintRequestQuery DTO', function () {
    $dto = new SmartbillPrintRequestQuery(series: 'S', number: '123', companyVatCode: 'RO123');
    expect($dto->toArray())->toBe([
        'seriesname' => 'S',
        'number' => '123',
        'cif' => 'RO123',
    ]);
});

it('serializes SmartbillInvoiceRequestBody DTO with nested client & products', function () {
    $client = new SmartbillClient('John', '-', false, 'Str 1', 'Cluj', 'Cluj', 'Romania', 'a@b.c', false);
    $products = [
        new SmartbillProduct('Prod', 'SKU1', 'buc', 'RON', 1, 100.0, true, 'TVA', 19, false, false),
    ];
    $dto = new SmartbillInvoiceRequestBody('RO123', 'S', $client, $products);

    $arr = $dto->toArray();
    expect($arr)->toHaveKeys(['companyVatCode', 'seriesName', 'client', 'products'])
        ->and($arr['client'])->toHaveKeys(['name', 'vatCode'])
        ->and($arr['products'][0])->toHaveKeys(['name', 'code', 'measuringUnitName']);
});
