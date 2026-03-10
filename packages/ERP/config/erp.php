<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global ERP Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Disable this to completely turn off all ERP provider logic.
    |
    */
    'enabled' => env('ERP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | ERP Providers
    |--------------------------------------------------------------------------
    |
    | The ERP providers available in the system.
    | Each provider must have a separate configuration file
    |
    */
    'providers' => [],

    /*
    |--------------------------------------------------------------------------
    | Sync Schedule Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how often the ERP sync should run
    |
    */
    'schedule' => [
        'products' => env('ERP_SYNC_PRODUCTS_SCHEDULE', '*/10 * * * *'), // Every 10 minutes
        'orders' => env('ERP_SYNC_ORDERS_SCHEDULE', '*/5 * * * *'),     // Every 5 minutes
        'stock' => env('ERP_SYNC_STOCK_SCHEDULE', '*/1 * * * *'),      // Every minute
        'localities' => env('ERP_SYNC_LOCALITIES_SCHEDULE', '0 0 * * 0'), // Every week (Sunday at midnight)
        'attributes' => env('ERP_SYNC_ATTRIBUTES_SCHEDULE', '*/9 * * * *'),  // Every 9 minutes to get before syncing products
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Features
    |--------------------------------------------------------------------------
    |
    | Define which ERP providers handle sync operations.
    | Leave empty to disable a feature or list one or more providers.
    |
    */
    'sync' => [
        'products' => [],
        'orders' => [],
        'stock' => [],
        'localities' => [],
        'attributes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Action Features
    |--------------------------------------------------------------------------
    |
    | Define which ERP providers handle actions.
    | Leave empty to disable a feature or list one or more providers.
    |
    */
    'actions' => [
        'send_order' => [],
        'billing' => [],
    ],
];
