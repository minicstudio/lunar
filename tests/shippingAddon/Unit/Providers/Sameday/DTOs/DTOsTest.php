<?php

use Lunar\Addons\Shipping\Providers\Sameday\DTOs\SamedayAWBRecipient;
use Lunar\Addons\Shipping\Providers\Sameday\DTOs\SamedayAWBRequestBody;
use Lunar\Addons\Shipping\Providers\Sameday\DTOs\SamedayParcel;

it('serializes Sameday parcel with optional dims', function () {
    $dto = new SamedayParcel(weight: 2.5, height: 10, width: 20, length: 30);

    expect($dto->toArray())->toBe([
        'weight' => 2.5,
        'height' => 10,
        'width' => 20,
        'length' => 30,
    ]);
});

it('serializes Sameday recipient', function () {
    $recipient = new SamedayAWBRecipient(
        name: 'ACME SRL',
        phoneNumber: '+40123456789',
        personType: 1,
        companyName: 'ACME SRL',
        postalCode: '010101',
        countyString: 'Bucuresti',
        cityString: 'Sector 1',
        address: 'Str. Test 1',
        email: 'office@acme.tld',
    );

    expect($recipient->toArray())->toBe([
        'name' => 'ACME SRL',
        'phoneNumber' => '+40123456789',
        'personType' => 1,
        'companyName' => 'ACME SRL',
        'postalCode' => '010101',
        'countyString' => 'Bucuresti',
        'cityString' => 'Sector 1',
        'address' => 'Str. Test 1',
        'email' => 'office@acme.tld',
    ]);
});

it('serializes Sameday AWB request body', function () {
    $payload = new SamedayAWBRequestBody(
        pickupPoint: 10,
        packageType: 0,
        packageWeight: 3.4,
        service: 7,
        serviceTaxes: ['PDO'],
        awbPayment: 1,
        cashOnDelivery: 0.0,
        insuredValue: 0,
        thirdPartyPickup: 0,
        awbRecipient: new SamedayAWBRecipient(
            name: 'John',
            phoneNumber: '0700',
            personType: 0,
            companyName: null,
            postalCode: '400000',
            countyString: 'Cluj',
            cityString: 'Cluj-Napoca',
            address: 'Str. Test 2',
            email: 'john@example.com',
        ),
        parcels: [new SamedayParcel(1.0)],
        contactPerson: 20,
        packageNumber: 1,
        clientInternalReference: 'ORDER-1',
        observation: 'Obs',
        oohLastMile: null,
    );

    $array = $payload->toArray();
    expect($array)->toHaveKeys(['pickupPoint', 'packageType', 'packageWeight', 'service', 'serviceTaxes', 'awbPayment', 'cashOnDelivery', 'insuredValue', 'thirdPartyPickup', 'awbRecipient', 'parcels', 'contactPerson', 'packageNumber', 'clientInternalReference', 'observation', 'oohLastMile'])
        ->and($array['awbRecipient'])->toHaveKeys(['name', 'phoneNumber', 'personType', 'companyName', 'postalCode', 'countyString', 'cityString', 'address', 'email'])
        ->and($array['parcels'][0])->toBe(['weight' => 1.0, 'height' => null, 'width' => null, 'length' => null]);
});
