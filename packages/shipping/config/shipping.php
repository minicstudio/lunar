<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global Shipping Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Disable this to completely turn off all shipping provider logic.
    |
    */
    'enabled' => env('SHIPPING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Shipping Locker Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Disable this to turn off the shipping locker functionality.
    |
    */
    'locker_enabled' => env('SHIPPING_LOCKER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Contact recipients
    |--------------------------------------------------------------------------
    |
    | Used for Nominatim User-Agent header and other contact purposes.
    |
    */
    'contact_recipients' => explode(',', env('SHIPPING_CONTACT_EMAIL', '')),

    /*
    |--------------------------------------------------------------------------
    | Shipping Providers
    |--------------------------------------------------------------------------
    |
    | The shipping providers available in the system.
    | Each provider must have a separate configuration file
    |
    */
    'providers' => [
        'sameday',
        'dpd',
        'pickup',
        'inhouse',
    ],

    /*
    |--------------------------------------------------------------------------
    | AWB Generation Status
    |--------------------------------------------------------------------------
    |
    | The order status that triggers automatic AWB generation.
    | When an order transitions to this status, the system will automatically
    | generate an AWB if one doesn't already exist.
    |
    */
    'generate_awb_on_status' => env('SHIPPING_AWB_GENERATION_STATUS', 'prepare-shipment'),
];
