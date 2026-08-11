<?php

use Lunar\Content\Support\ContentMediaDefinitions;

return [
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Announcement cache TTL (seconds)
    |--------------------------------------------------------------------------
    | How long active announcements / heroes / popups / menu items / FAQ items are cached in the storefront.
    */
    'announcement_cache_ttl' => 300,

    /*
    |--------------------------------------------------------------------------
    | Content Media Definitions
    |--------------------------------------------------------------------------
    | Media definitions class for content block images (e.g. hero slides).
    */
    'media_definitions' => ContentMediaDefinitions::class,

    /*
    |--------------------------------------------------------------------------
    | Content Upload Disk
    |--------------------------------------------------------------------------
    | Filesystem disk used for hero (and future content) media uploads.
    */
    'upload_disk' => env('CONTENT_UPLOAD_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Default announcement background color
    |--------------------------------------------------------------------------
    | Optional override for the admin ColorPicker default. When null, falls
    | back to lunar-frontend brand/payment color config.
    */
    'announcement_default_bg_color' => null,

    /*
    |--------------------------------------------------------------------------
    | Default announcement text color
    |--------------------------------------------------------------------------
    | Optional override for the admin ColorPicker default.
    */
    'announcement_default_text_color' => '#ffffff',
];
