<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Klaviyo Integration Enabled
    |--------------------------------------------------------------------------
    */

    'enabled' => env('KLAVIYO_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Klaviyo Private API Key
    |--------------------------------------------------------------------------
    | Private key only (starts with pk_). Do NOT include the "Klaviyo-API-Key"
    | prefix — the connector adds Authorization: Klaviyo-API-Key {key}.
    */

    'api_key' => env('KLAVIYO_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | API Revision
    |--------------------------------------------------------------------------
    | Pin a current GA revision with remaining support.
    | Forbidden default: 2024-10-15 (retires 2026-10-15).
    */

    'api_revision' => env('KLAVIYO_API_REVISION', '2026-01-15'),

    /*
    |--------------------------------------------------------------------------
    | List ID (Explicit Opt-In / Double Opt-In)
    |--------------------------------------------------------------------------
    | Required for ExplicitOptIn (footer, registration checkbox, checkout newsletter).
    | Must be configured as a double opt-in list in Klaviyo so confirmation email is sent.
    */

    'list_id' => env('KLAVIYO_LIST_ID'),

    /*
    |--------------------------------------------------------------------------
    | Automatic List ID (Customer Registration / Single Opt-In)
    |--------------------------------------------------------------------------
    | Required for CustomerRegistration (automatic registration or order policy).
    | Must be configured as a single opt-in list in Klaviyo for immediate subscribe.
    | Never use historical_import on the DOI list as a substitute.
    */

    'automatic_list_id' => env('KLAVIYO_AUTOMATIC_LIST_ID'),

    /*
    |--------------------------------------------------------------------------
    | Subscriber / Profile Sync
    |--------------------------------------------------------------------------
    */

    'sync_subscribers' => env('KLAVIYO_SYNC_SUBSCRIBERS', false),

    /*
    |--------------------------------------------------------------------------
    | Order Sync
    |--------------------------------------------------------------------------
    */

    'sync_orders' => env('KLAVIYO_SYNC_ORDERS', false),

    /*
    |--------------------------------------------------------------------------
    | Product Catalog Sync
    |--------------------------------------------------------------------------
    | Sync Lunar products to Klaviyo Catalogs API for email product recommendations.
    */

    'sync_products' => env('KLAVIYO_SYNC_PRODUCTS', false),

    /*
    |--------------------------------------------------------------------------
    | Catalog Defaults
    |--------------------------------------------------------------------------
    | Fallback category external_id when a product has no collections.
    | Use alphanumeric only — Klaviyo strips special characters on categories.
    */

    'catalog' => [
        'default_category_external_id' => env('KLAVIYO_CATALOG_DEFAULT_CATEGORY', 'uncategorized'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Tracking
    |--------------------------------------------------------------------------
    */

    'track_events' => env('KLAVIYO_TRACK_EVENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Profile Attribute Mapping
    |--------------------------------------------------------------------------
    | Maps neutral property keys to Klaviyo profile property keys.
    */

    'profile_attributes' => [
        'language' => 'language',
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Metric Names
    |--------------------------------------------------------------------------
    */

    'placed_order_metric' => env('KLAVIYO_PLACED_ORDER_METRIC', 'Placed Order'),

    'ordered_product_metric' => env('KLAVIYO_ORDERED_PRODUCT_METRIC', 'Ordered Product'),

    /*
    |--------------------------------------------------------------------------
    | Debug Logging
    |--------------------------------------------------------------------------
    | When true, logs listener/job skips, dispatches, and API outcomes to the
    | default log channel (prefix "[Klaviyo]"). Never logs the API key.
    */

    'debug' => env('KLAVIYO_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */

    'retry' => [
        'max_attempts' => env('KLAVIYO_MAX_ATTEMPTS', 4),
        'backoff' => [60, 300, 3600],
    ],
];
