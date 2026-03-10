<?php

use Lunar\Addons\Shipping\Providers\Pickup\PickupApiClient;

test('getProviderName returns pickup', function () {
    $client = new PickupApiClient;

    expect($client->getProviderName())->toBe('pickup');
});

test('generateAWB returns null awbNumber', function () {
    $client = new PickupApiClient;

    $result = $client->generateAWB(null);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('awbNumber');
    expect($result['awbNumber'])->toBeNull();
});

test('downloadAWBPDF returns null', function () {
    $client = new PickupApiClient;

    $result = $client->downloadAWBPDF('any-awb-number');

    expect($result)->toBeNull();
});
