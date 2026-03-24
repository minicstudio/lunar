<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sameday Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Disable this to turn off only the Sameday shipping provider logic.
    |
    */
    'enabled' => env('SAMEDAY_ENABLED', false),

     /*
    |--------------------------------------------------------------------------
    | Shipping Provider Class
    |--------------------------------------------------------------------------
    |
    | The class responsible for implementing the shipping provider logic.
    |
    */
    'provider_class' => \Lunar\Addons\Shipping\Providers\Sameday\SamedayShippingProvider::class,

    /*
    |--------------------------------------------------------------------------
    | API Client Class
    |--------------------------------------------------------------------------
    |
    | The class used to communicate with the external shipping provider's API.
    |
    */
    'client_class' => \Lunar\Addons\Shipping\Providers\Sameday\SamedayApiClient::class,

    /*
    |--------------------------------------------------------------------------
    | Sameday API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Sameday API endpoints.
    |
    */
    'base_url' => env('SAMEDAY_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Sameday Username
    |--------------------------------------------------------------------------
    |
    | Your Sameday account username used for authentication.
    |
    */
    'username' => env('SAMEDAY_USERNAME'),

    /*
    |--------------------------------------------------------------------------
    | Sameday Password
    |--------------------------------------------------------------------------
    |
    | Your Sameday account password used for authentication.
    |
    */
    'password' => env('SAMEDAY_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Pickup Point ID
    |--------------------------------------------------------------------------
    |
    | The ID of the Sameday pickup point to be used for shipments.
    |
    */
    'pickup_point_id' => env('SAMEDAY_PICKUP_POINT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Contact Person ID
    |--------------------------------------------------------------------------
    |
    | The ID of the contact person at the pickup point, required for shipment creation.
    |
    */
    'contact_person_id' => env('SAMEDAY_CONTACT_PERSON_ID'),

    /*
    |--------------------------------------------------------------------------
    | Provider's page URL
    |--------------------------------------------------------------------------
    |
    | The URL to the Sameday provider's page for checking shipment status or details by AWB number.
    |
    */
    'provider_page_url' => env('SAMEDAY_PROVIDER_PAGE_URL', 'https://sameday.ro/#awb='),

    /*
    |--------------------------------------------------------------------------
    | Personal drop off
    |--------------------------------------------------------------------------
    |
    | Whether the packages can be dropped off personally at the Sameday easybox.
    |
    */
    'personal_drop_off' => env('SAMEDAY_PDO', false),

    /*
    |--------------------------------------------------------------------------
    | Default Service IDs
    |--------------------------------------------------------------------------
    |
    | These IDs correspond to the Sameday service types used for different shipping methods.
    |
    */
    'home_shipping_id' => env('SAMEDAY_HOME_SHIPPING_ID', 7), // Default service ID for home shipping

    'locker_shipping_id' => env('SAMEDAY_LOCKER_SHIPPING_ID', 15), // Default service ID for locker shipping
];
