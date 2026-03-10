<?php

return [
    /*
    |--------------------------------------------------------------------------
    | In-house shipping Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Disable this to turn off only the In-house shipping provider logic.
    |
    */
    'enabled' => env('IN_HOUSE_SHIPPING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Shipping Provider Class
    |--------------------------------------------------------------------------
    |
    | The class responsible for implementing the shipping provider logic.
    |
    */
    'provider_class' => \Lunar\Addons\Shipping\Providers\InHouse\InHouseShippingProvider::class,

    /*
    |--------------------------------------------------------------------------
    | API Client Class
    |--------------------------------------------------------------------------
    |
    | The class used to communicate with the external shipping provider's API.
    | For in-house shipping, this is just a stub as no external API is needed.
    |
    */
    'client_class' => \Lunar\Addons\Shipping\Providers\InHouse\InHouseApiClient::class,
];
