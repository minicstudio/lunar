<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Personal Pickup Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Disable this to turn off only the Personal Pickup shipping provider logic.
    |
    */
    'enabled' => env('PERSONAL_PICKUP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Shipping Provider Class
    |--------------------------------------------------------------------------
    |
    | The class responsible for implementing the shipping provider logic.
    |
    */
    'provider_class' => \Lunar\Addons\Shipping\Providers\Pickup\PickupShippingProvider::class,

    /*
    |--------------------------------------------------------------------------
    | API Client Class
    |--------------------------------------------------------------------------
    |
    | The class used to communicate with the external shipping provider's API.
    | For personal pickup, this is just a stub as no external API is needed.
    |
    */
    'client_class' => \Lunar\Addons\Shipping\Providers\Pickup\PickupApiClient::class,
];
