<?php

use Lunar\Review\Generators\ReviewPathGenerator;
use Lunar\Review\Support\ReviewMediaDefinitions;

return [
    /*
    |--------------------------------------------------------------------------
    | Review Media Definitions
    |--------------------------------------------------------------------------
    | This setting defines the media definitions class for review images.
    |
    */
    'media_definitions' => ReviewMediaDefinitions::class,

    /*
    |--------------------------------------------------------------------------
    | Review Path Generator
    |--------------------------------------------------------------------------
    | This setting defines the path generator class for organizing review media files.
    |
    */
    'path_generator' => ReviewPathGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Review Upload Disk
    |--------------------------------------------------------------------------
    | This setting defines the filesystem disk to be used for storing review images.
    | Set this to the desired disk (e.g. "public", "s3", etc.).
    |
    */
    'upload_disk' => env('REVIEW_UPLOAD_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Maximum File Uploads
    |--------------------------------------------------------------------------
    | This setting defines the maximum number of files a user can upload for a review.
    |
    */
    'max_files' => env('REVIEW_MAX_FILES', 15),

    /*
    |--------------------------------------------------------------------------
    | Available Reviewable Types for Reviews
    |--------------------------------------------------------------------------
    | This array defines the available reviewable model types that can be enabled.
    |
    */
    'available_types' => [
        \Lunar\Models\ProductVariant::class,
        \Lunar\Models\Channel::class,
    ],
];
