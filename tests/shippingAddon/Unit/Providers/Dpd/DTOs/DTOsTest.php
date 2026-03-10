<?php

use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdAdditionalServices;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdAddress;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdAWBRequestBody;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdCODAdditionalService;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdContent;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdParcelRef;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdParcelToPrint;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdPayment;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdPhoneNumber;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdPrintRequestBody;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdRecipient;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdService;

it('serializes DPD phone number', function () {
    $dto = new DpdPhoneNumber(number: '+40123456789');
    expect($dto->toArray())->toBe(['number' => '+40123456789']);
});

it('serializes DPD address', function () {
    $dto = new DpdAddress(siteName: 'Bucharest', postCode: '010101', addressNote: 'Str. Test 1');
    expect($dto->toArray())->toBe([
        'siteName' => 'Bucharest',
        'postCode' => '010101',
        'addressNote' => 'Str. Test 1',
    ]);
});

it('serializes DPD recipient with nullable fields filtered', function () {
    $recipient = new DpdRecipient(
        phone: new DpdPhoneNumber(number: '+40123456789'),
        clientName: 'ACME SRL',
        contactName: null,
        email: null,
        privatePerson: false,
        address: new DpdAddress(siteName: 'Cluj', postCode: '400000', addressNote: 'Note')
    );

    $arr = $recipient->toArray();

    expect($arr)->toHaveKeys(['phone1', 'clientName', 'privatePerson', 'address'])
        ->and($arr)->not()->toHaveKey('contactName')
        ->and($arr)->not()->toHaveKey('email');
});

it('serializes DPD service and additional services', function () {
    $service = new DpdService(
        autoAdjustPickupDate: true,
        additionalServices: new DpdAdditionalServices(
            cod: new DpdCODAdditionalService(amount: 12.5)
        ),
        serviceId: 99
    );

    expect($service->toArray())->toBe([
        'autoAdjustPickupDate' => true,
        'serviceId' => 99,
        'additionalServices' => ['cod' => ['amount' => 12.5]],
    ]);
});

it('serializes DPD content and payment', function () {
    $content = new DpdContent(parcelsCount: 2, totalWeight: 3.4, contents: 'Electronics', package: 'BOX');
    $payment = new DpdPayment(courierServicePayer: 'SENDER', packagePayer: 'RECIPIENT');

    expect($content->toArray())->toBe([
        'parcelsCount' => 2,
        'totalWeight' => 3.4,
        'contents' => 'Electronics',
        'package' => 'BOX',
    ])->and($payment->toArray())->toBe([
        'courierServicePayer' => 'SENDER',
        'packagePayer' => 'RECIPIENT',
    ]);
});

it('serializes DPD AWB request body', function () {
    $payload = new DpdAWBRequestBody(
        userName: 'user',
        password: 'pass',
        language: 'EN',
        recipient: new DpdRecipient(
            phone: new DpdPhoneNumber(number: '0700'),
            clientName: 'John',
            contactName: null,
            email: 'john@example.com',
            privatePerson: true,
            address: new DpdAddress(siteName: 'Arad', postCode: '310000', addressNote: 'Addr')
        ),
        service: new DpdService(
            autoAdjustPickupDate: true,
            additionalServices: new DpdAdditionalServices(new DpdCODAdditionalService(0)),
            serviceId: 1
        ),
        content: new DpdContent(1, 2.0, 'Books', 'BOX'),
        payment: new DpdPayment('SENDER', 'RECIPIENT'),
        shipmentNote: 'Leave at door',
    );

    $arr = $payload->toArray();
    expect($arr)->toHaveKeys(['userName', 'password', 'language', 'recipient', 'service', 'content', 'payment', 'shipmentNote'])
        ->and($arr['recipient'])->toHaveKeys(['phone1', 'clientName', 'email', 'privatePerson', 'address']);
});

it('serializes DPD print payload and nested parcel refs', function () {
    $payload = new DpdPrintRequestBody(
        userName: 'user',
        password: 'pass',
        paperSize: 'A4',
        parcels: [new DpdParcelToPrint(new DpdParcelRef('AWB123'))]
    );

    expect($payload->toArray())->toBe([
        'userName' => 'user',
        'password' => 'pass',
        'paperSize' => 'A4',
        'parcels' => [
            ['parcel' => ['id' => 'AWB123']],
        ],
    ]);
});
