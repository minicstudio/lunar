<?php

use Lunar\Base\StandardMediaDefinitions;

return [

    'definitions' => [
        'asset' => StandardMediaDefinitions::class,
        'brand' => StandardMediaDefinitions::class,
        'collection' => StandardMediaDefinitions::class,
        'product' => StandardMediaDefinitions::class,
        'product-option' => StandardMediaDefinitions::class,
        'product-option-value' => StandardMediaDefinitions::class,
    ],

    'collection' => 'images',

    'fallback' => [
        'url' => env('FALLBACK_IMAGE_URL', null),
        'path' => env('FALLBACK_IMAGE_PATH', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configure file upload constraints, size limits, and allowed file types
    | for media uploads throughout the application.
    |
    */

    'max_file_size' => 10240,

    'accepted_file_types' => [
        'image/jpeg',
        'image/png',
        'image/webp',
    ],

    'allowed_file_extensions' => [
        'jpg', 'jpeg', 'png', 'webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | File security validation to prevent malicious uploads and protect
    | against common file upload vulnerabilities.
    |
    */

    'dangerous_file_extensions' => [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8',
        'phar', 'exe', 'dll', 'js', 'html', 'htm',
    ],
];
