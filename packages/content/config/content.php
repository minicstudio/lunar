<?php

return [
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Announcement cache TTL (seconds)
    |--------------------------------------------------------------------------
    | How long active announcements are cached in the storefront.
    */
    'announcement_cache_ttl' => 300,

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
