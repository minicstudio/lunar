<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DPD Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Disable this to turn off only the DPD shipping provider logic.
    |
    */
    'enabled' => env('DPD_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Shipping Provider Class
    |--------------------------------------------------------------------------
    |
    | The class responsible for implementing the shipping provider logic.
    |
    */
    'provider_class' => \Lunar\Addons\Shipping\Providers\Dpd\DpdShippingProvider::class,

    /*
    |--------------------------------------------------------------------------
    | API Client Class
    |--------------------------------------------------------------------------
    |
    | The class used to communicate with the external shipping provider's API.
    |
    */
    'client_class' => \Lunar\Addons\Shipping\Providers\Dpd\DpdApiClient::class,

    /*
    |--------------------------------------------------------------------------
    | DPD API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the DPD API endpoints.
    |
    */
    'base_url' => env('DPD_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | DPD Username
    |--------------------------------------------------------------------------
    |
    | Your DPD account username used for authentication.
    |
    */
    'username' => env('DPD_USERNAME'),

    /*
    |--------------------------------------------------------------------------
    | DPD Password
    |--------------------------------------------------------------------------
    |
    | Your DPD account password used for authentication.
    |
    */
    'password' => env('DPD_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | DPD Service ID
    |--------------------------------------------------------------------------
    |
    | The ID of the DPD service to be used for shipments.
    |
    */
    'service_id' => env('DPD_SERVICE_ID'),

    /*
    |--------------------------------------------------------------------------
    | DPD Contents
    |--------------------------------------------------------------------------
    |
    | A short, human-readable description of what the parcel contains;
    | required by DPD and limited to 100 characters.
    |
    | e.g., 'Books', 'Clothing – T-shirts', 'Electronics – USB cables'
    |
    */
    'contents' => env('DPD_CONTENTS'),

    /*
    |--------------------------------------------------------------------------
    | DPD Package Type
    |--------------------------------------------------------------------------
    |
    | The type of package used for the shipment, e.g., box, envelope, etc.
    |
    */
    'package' => env('DPD_PACKAGE'),

    /*
    |--------------------------------------------------------------------------
    | DPD Paper Size
    |--------------------------------------------------------------------------
    |
    | The paper size for the AWB PDF, e.g., A4, A5, etc.
    |
    */
    'paper_size' => env('DPD_PAPER_SIZE', 'A4'),
];
