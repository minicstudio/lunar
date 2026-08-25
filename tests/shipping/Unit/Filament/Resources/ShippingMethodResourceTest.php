<?php

uses(\Lunar\Tests\Shipping\TestCase::class);

use Lunar\Shipping\Filament\Resources\ShippingMethodResource;

test('code select options are sourced dynamically from the shipping providers config', function () {
    config(['lunar.shipping.providers' => ['dpd', 'inhouse']]);

    $options = ShippingMethodResource::getCodeFormComponent()->getOptions();

    expect($options)->toBe([
        'dpd' => 'Dpd',
        'inhouse' => 'Inhouse',
    ]);
});

test('code select adds a headline-labelled locker option for providers that support it', function () {
    config([
        'lunar.shipping.providers' => ['sameday'],
        'lunar.shipping.sameday.supports_locker' => true,
    ]);

    $options = ShippingMethodResource::getCodeFormComponent()->getOptions();

    expect($options)->toBe([
        'sameday' => 'Sameday',
        'sameday-locker' => 'Sameday Locker',
    ]);
});

test('code select omits the locker option when supports_locker is missing or false', function () {
    config([
        'lunar.shipping.providers' => ['dpd', 'pickup'],
        'lunar.shipping.pickup.supports_locker' => false,
    ]);

    $options = ShippingMethodResource::getCodeFormComponent()->getOptions();

    expect($options)->toBe([
        'dpd' => 'Dpd',
        'pickup' => 'Pickup',
    ]);
});
