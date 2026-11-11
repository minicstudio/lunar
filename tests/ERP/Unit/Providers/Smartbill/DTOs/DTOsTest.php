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

it('serializes productDescription on SmartbillProduct only when set', function () {
    $dto = new SmartbillProduct(
        name: 'Pack',
        code: 'P4',
        measuringUnitName: 'pachet',
        currency: 'RON',
        quantity: 2,
        price: 99.99,
        isTaxIncluded: false,
        taxName: 'Normala',
        taxPercentage: 21.0,
        saveToDb: false,
        isService: false,
        productDescription: '1 pachet = 4 buc',
    );

    expect($dto->toArray())->toMatchArray(['productDescription' => '1 pachet = 4 buc'])
        ->and((new SmartbillProduct('Prod', 'P1', 'buc', 'RON', 1, 10.0, false, 'Normala', 21.0, false, false, ''))->toArray())
        ->not->toHaveKey('productDescription');
});

it('leaves the VAT fields out of SmartbillProduct when no tax percentage is set', function () {
    $dto = new SmartbillProduct('Prod', 'P1', 'buc', 'RON', 1, 10.0, false, null, null, false, false);

    expect($dto->toArray())->toBe([
        'name' => 'Prod',
        'code' => 'P1',
        'measuringUnitName' => 'buc',
        'currency' => 'RON',
        'quantity' => 1.0,
        'price' => 10.0,
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
    $dto = new SmartbillInvoiceRequestBody('RO123', 'S', $client, $products, '');

    $arr = $dto->toArray();
    expect($arr)->toHaveKeys(['companyVatCode', 'seriesName', 'client', 'products', 'observations'])
        ->and($arr['client'])->toHaveKeys(['name', 'vatCode'])
        ->and($arr['products'][0])->toHaveKeys(['name', 'code', 'measuringUnitName']);
});

it('serializes empty observations on SmartbillInvoiceRequestBody', function () {
    $client = new SmartbillClient('John', '-', false, 'Str 1', 'Cluj', 'Cluj', 'Romania', 'a@b.c', false);
    $dto = new SmartbillInvoiceRequestBody('RO123', 'S', $client, [], '');
    expect($dto->toArray()['observations'])->toBe('');
});

it('includes observations on SmartbillInvoiceRequestBody when set', function () {
    $client = new SmartbillClient('John', '-', false, 'Str 1', 'Cluj', 'Cluj', 'Romania', 'a@b.c', false);
    $dto = new SmartbillInvoiceRequestBody('RO123', 'S', $client, [], '#1525_card_dpd');
    expect($dto->toArray()['observations'])->toBe('#1525_card_dpd');
});
