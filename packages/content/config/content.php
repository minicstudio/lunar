<?php

use Lunar\Content\Support\ContentMediaDefinitions;

return [
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Admin resources
    |--------------------------------------------------------------------------
    | Toggle which Content items appear in the Filament admin navigation.
    | Disabled resources are not registered (no nav item and no admin routes).
    | Storefront helpers are unchanged — they still read DB rows when present.
    | Missing keys default to enabled.
    */
    'resources' => [
        'announcement' => true,
        'hero' => true,
        'menu_item' => true,
        'popup' => true,
        'faq_item' => true,
        'contact_info' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Announcement cache TTL (seconds)
    |--------------------------------------------------------------------------
    | How long active announcements / heroes / popups / menu items / FAQ / contact details are cached in the storefront.
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
    | Content Rich Text Attachments
    |--------------------------------------------------------------------------
    | Disk and directory for inline images uploaded via TranslatedText rich editors
    | in the Content admin resources (contact intro, FAQ answers, announcements).
    */
    'richtext_attachments_disk' => env('CONTENT_RICHTEXT_ATTACHMENTS_DISK', env('CONTENT_UPLOAD_DISK', env('MEDIA_DISK', 's3'))),
    'richtext_attachments_directory' => env('CONTENT_RICHTEXT_ATTACHMENTS_DIRECTORY', 'content/richtext'),

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
