<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feedback enabled
    |--------------------------------------------------------------------------
    |
    | Controls whether feedback is collected and shown in the admin panel.
    | Hosts register the Filament plugin only when this is true, and the
    | order list / order detail extensions are skipped when it is false.
    |
    */
    'enabled' => env('FEEDBACK_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Score scale
    |--------------------------------------------------------------------------
    |
    | Inclusive integer range shown as numbered buttons. scale_min must be
    | less than or equal to scale_max.
    |
    */
    'scale_min' => (int) env('FEEDBACK_SCALE_MIN', 1),
    'scale_max' => (int) env('FEEDBACK_SCALE_MAX', 5),

    /*
    |--------------------------------------------------------------------------
    | Low-score threshold
    |--------------------------------------------------------------------------
    |
    | When the selected score is less than or equal to this value, an
    | optional comment may be stored. Scores above it save without a comment.
    |
    */
    'threshold' => (int) env('FEEDBACK_THRESHOLD', 3),

    /*
    |--------------------------------------------------------------------------
    | Types
    |--------------------------------------------------------------------------
    |
    | Keys for the flow that asked for feedback. At most one feedback row is
    | stored per (order_id, type).
    |
    */
    'types' => [
        'checkout_shopping_experience' => 'checkout.shopping_experience',
    ],

    /*
    |--------------------------------------------------------------------------
    | Comment
    |--------------------------------------------------------------------------
    */
    'comment_max' => 2000,

    /*
    |--------------------------------------------------------------------------
    | Rate limit
    |--------------------------------------------------------------------------
    |
    | Max submit attempts per order id + IP within the decay window.
    |
    */
    'rate_limit' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],
];
