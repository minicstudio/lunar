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

    /*
    |--------------------------------------------------------------------------
    | Order Status for Email Dispatch
    |--------------------------------------------------------------------------
    | This setting defines the order status at which the email dispatching
    | should be triggered after the configured delay.
    |
    */
    'order_status_for_review_reminder' => env('ORDER_STATUS_FOR_REVIEW_REMINDER', 'completed'),

    /*
    |---------------------------------------------------------------------------
    | Review Reminder Mailer
    |---------------------------------------------------------------------------
    | This setting defines which mailer class should be used for review
    | reminders. Set it to a fully qualified class name. The mailer class
    | constructor must accept an Order parameter.
    |
    */
    'review_reminder_mailer' => null,

    /*
    |---------------------------------------------------------------------------
    | Reminder Delays (Minutes)
    |---------------------------------------------------------------------------
    | These settings define the delays for sending review reminder emails:
    | - 'first_reminder_delay_minutes': Number of minutes after the order status is
    |   updated to the configured status before the first reminder email is sent.
    | - 'second_reminder_delay_minutes': Number of minutes after the order status is
    |   updated to the configured status before the second reminder email is sent.
    |
    */
    'first_reminder_delay_minutes' => env('FIRST_REMINDER_DELAY_MINUTES', 15 * 24 * 60),
    'second_reminder_delay_minutes' => env('SECOND_REMINDER_DELAY_MINUTES', 30 * 24 * 60),
];
