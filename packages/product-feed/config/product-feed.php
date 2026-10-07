<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | Public feed routes are registered under this prefix.
    | Default: GET /feeds/products/{format}
    |
    */

    'route_prefix' => env('PRODUCT_FEED_ROUTE_PREFIX', 'feeds'),

    /*
    |--------------------------------------------------------------------------
    | Product URL Resolution
    |--------------------------------------------------------------------------
    |
    | Absolute storefront links are built from Lunar URL slugs plus one of:
    | - a named Laravel route that accepts a {slug} parameter
    | - an absolute URL template with {base_url} and {slug} placeholders
    |
    */

    'product_url' => [
        'route' => env('PRODUCT_FEED_PRODUCT_ROUTE'),
        'template' => env('PRODUCT_FEED_PRODUCT_URL_TEMPLATE', '{base_url}/{slug}'),
        'base_url' => env('PRODUCT_FEED_BASE_URL', env('APP_URL')),
    ],

    /*
    |--------------------------------------------------------------------------
    | XML Channel Metadata
    |--------------------------------------------------------------------------
    */

    'xml' => [
        'title' => env('PRODUCT_FEED_XML_TITLE', 'Product Feed'),
        'description' => env('PRODUCT_FEED_XML_DESCRIPTION', 'Product catalog feed'),
        'link' => env('PRODUCT_FEED_XML_LINK', env('APP_URL')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Body Cache
    |--------------------------------------------------------------------------
    |
    | Cache the fully encoded feed body per format (csv/xml/json) so crawlers
    | do not rebuild the catalog on every hit. TTL-only invalidation in v1;
    | call ProductFeedCache::forgetAll() to flush manually.
    |
    */

    'cache' => [
        'enabled' => env('PRODUCT_FEED_CACHE_ENABLED', true),
        'ttl' => (int) env('PRODUCT_FEED_CACHE_TTL', 3600),
        'key_prefix' => env('PRODUCT_FEED_CACHE_KEY_PREFIX', 'lunar.product-feed'),
        'store' => env('PRODUCT_FEED_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog Query
    |--------------------------------------------------------------------------
    |
    | Products are loaded via lazyById() in chunks so large catalogs do not
    | hydrate the full Eloquent graph in one go.
    |
    */

    'query' => [
        'chunk_size' => (int) env('PRODUCT_FEED_CHUNK_SIZE', 100),
    ],

];
