<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mailchimp Integration Enabled
    |--------------------------------------------------------------------------
    | Enable or disable the Mailchimp integration. When disabled, no API calls
    | will be made and no jobs will be dispatched.
    */

    'enabled' => env('MAILCHIMP_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Mailchimp API Key
    |--------------------------------------------------------------------------
    | Your Mailchimp API key. You can generate one from your Mailchimp account
    | under Account > Extras > API keys.
    */

    'api_key' => env('MAILCHIMP_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Mailchimp API Server
    |--------------------------------------------------------------------------
    | The server prefix for your Mailchimp account (e.g., 'us1', 'us2', etc.).
    | This is typically found in your API key after the dash.
    */

    'server' => env('MAILCHIMP_SERVER', 'us1'),

    /*
    |--------------------------------------------------------------------------
    | Mailchimp Audience (List) ID
    |--------------------------------------------------------------------------
    | The default audience/list ID where subscribers will be added.
    | You can find this in your Mailchimp account under Audience > Settings.
    */

    'list_id' => env('MAILCHIMP_LIST_ID'),

    /*
    |--------------------------------------------------------------------------
    | Mailchimp Store ID
    |--------------------------------------------------------------------------
    | The store ID for the Mailchimp Ecommerce API. This should be an existing
    | store in your Mailchimp account. We reference this store but don't create it.
    */

    'store_id' => env('MAILCHIMP_STORE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Automatic Subscription
    |--------------------------------------------------------------------------
    | Automatically subscribe users to Mailchimp when they register (and verify their email) or place an order.
    |
    */
    'automatic_subscription' => env('MAILCHIMP_AUTOMATIC_SUBSCRIPTION', false),

    /*
    |--------------------------------------------------------------------------
    | Subscriber Sync
    |--------------------------------------------------------------------------
    | Sync users to your Mailchimp list/audience for email campaigns.
    | Subscribers are synced on registration and after orders (with preferences).
    */

    'sync_subscribers' => env('MAILCHIMP_SYNC_SUBSCRIBERS', false),

    /*
    |--------------------------------------------------------------------------
    | Product Sync
    |--------------------------------------------------------------------------
    | Sync products to Mailchimp Ecommerce API when created or updated.
    */

    'sync_products' => env('MAILCHIMP_SYNC_PRODUCTS', false),

    /*
    |--------------------------------------------------------------------------
    | Order Sync
    |--------------------------------------------------------------------------
    | Sync orders to Mailchimp Ecommerce API on successful checkout.
    | Note: Customer data is automatically included in order sync.
    */

    'sync_orders' => env('MAILCHIMP_SYNC_ORDERS', false),

    /*
    |--------------------------------------------------------------------------
    | Cart Sync (Abandoned Cart)
    |--------------------------------------------------------------------------
    | Sync carts to Mailchimp for abandoned cart emails.
    | Only logged-in users with email addresses will have their carts synced.
    */

    'sync_carts' => env('MAILCHIMP_SYNC_CARTS', false),

    /*
    |--------------------------------------------------------------------------
    | Event Tracking
    |--------------------------------------------------------------------------
    | Track custom events (e.g., begin_checkout) in Mailchimp for logged-in users.
    | These events can be used to trigger automations and personalized campaigns.
    */

    'track_events' => env('MAILCHIMP_TRACK_EVENTS', true),

    /*
    |--------------------------------------------------------------------------
    | Merge Fields
    |--------------------------------------------------------------------------
    | Define the merge field tags used in your Mailchimp audience.
    */

    'merge_fields' => [
        'first_name' => 'FNAME',
        'last_name' => 'LNAME',
        'phone' => 'PHONE',
        'address' => 'ADDRESS',
        'preferred_category' => 'PREFCAT',
        'preferred_subcategory' => 'PREFSUBCAT',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Option Merge Fields
    |--------------------------------------------------------------------------
    | Define custom merge fields based on your product options.
    | Each entry maps a Mailchimp merge field tag to a product option handle.
    | The system will extract the most frequent option value from user orders.
    |
    | Example for a clothing webshop:
    | 'SIZE' => [
    |     'handle' => 'size',
    |     'name' => 'Clothing Size',
    |     'type' => 'text',
    | ],
    */

    'option_fields' => [
        // Add your custom option fields here
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    | Configure retry behavior for failed API requests.
    */

    'retry' => [
        'max_attempts' => env('MAILCHIMP_MAX_ATTEMPTS', 4),
        'backoff' => [60, 300, 3600], // 1 min, 5 min, 1 hour
    ],
];
