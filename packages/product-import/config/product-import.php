<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Turns the AI-assisted product import on. The host app registers the
    | `ProductImportPlugin` only while this is true, and the prune command is
    | scheduled only while this is true.
    |
    */
    'enabled' => env('PRODUCT_IMPORT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | The private disk that holds uploaded files until they are pruned, the
    | largest accepted file in kilobytes, and the most data rows an import
    | may hold across its included sheets.
    |
    */
    'disk' => env('PRODUCT_IMPORT_DISK', 'local'),

    'max_file_size_kb' => (int) env('PRODUCT_IMPORT_MAX_FILE_SIZE_KB', 20480),

    'max_rows' => (int) env('PRODUCT_IMPORT_MAX_ROWS', 20000),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Reading, analysis, staging, and import jobs use the application's default
    | queue and connection unless these are set. When set, they move every
    | job, the commit chain, and its batch to that queue or connection, for
    | example a `product-import` queue on the database connection.
    |
    */
    'queue' => [
        'name' => env('PRODUCT_IMPORT_QUEUE'),
        'connection' => env('PRODUCT_IMPORT_QUEUE_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI column analysis
    |--------------------------------------------------------------------------
    |
    | `provider` is any `laravel/ai` provider name (openai, anthropic, gemini,
    | ollama, ...). An empty `model` means the provider's default model. An
    | empty `key` falls back to `ai.providers.<provider>.key`, so the AI
    | Assistant chat keeps its own key when `key` is set here. `url` points
    | the provider at another base URL.
    |
    | `fallback_models` is a comma-separated list of other models of the same
    | provider, tried in order when the model before them is overloaded or
    | rate limited (for example "gemini-3.5-flash,gemini-3.6-flash"). They
    | use the same key and URL as `model`.
    |
    | Only the headers, column statistics, and at most `sample_size` distinct
    | sample values of at most `sample_length` characters per column are sent,
    | enough for the AI to tell each column's number or date format and its
    | separators. No column type is guessed or sent.
    |
    | A suggested target with a confidence of at least `confidence.preselect`
    | is selected, and a selected target below `confidence.review` is
    | highlighted for review.
    |
    */
    'ai' => [
        'provider' => env('PRODUCT_IMPORT_AI_PROVIDER'),
        'model' => env('PRODUCT_IMPORT_AI_MODEL'),
        'fallback_models' => env('PRODUCT_IMPORT_AI_FALLBACK_MODELS'),
        'key' => env('PRODUCT_IMPORT_AI_API_KEY'),
        'url' => env('PRODUCT_IMPORT_AI_URL'),
        'timeout' => (int) env('PRODUCT_IMPORT_AI_TIMEOUT', 120),
        'sample_size' => 30,
        'sample_length' => 100,
        'confidence' => [
            'preselect' => 0.60,
            'review' => 0.85,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Value matching
    |--------------------------------------------------------------------------
    |
    | Categories, brands, and option values whose similarity to an existing
    | record reaches this share are marked as similar and need a decision.
    |
    */
    'matching' => [
        'similar_threshold' => 0.70,
    ],

    /*
    |--------------------------------------------------------------------------
    | Import profiles
    |--------------------------------------------------------------------------
    |
    | A saved profile is offered for a sheet when this share of the headers
    | match (shared headers divided by the larger header count).
    |
    */
    'profiles' => [
        'match_threshold' => 0.80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Commit
    |--------------------------------------------------------------------------
    |
    | How many product groups one queued commit job writes. Each group is
    | written in its own transaction.
    |
    */
    'commit' => [
        'groups_per_job' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | Image URLs from the file are downloaded after the products are written,
    | `per_job` images per queued job. Only HTTP and HTTPS URLs whose host
    | resolves to public addresses are fetched, redirects are checked the same
    | way, and JPEG, PNG, GIF, and WebP images up to `max_size_kb` kilobytes
    | that arrive within `timeout` seconds are accepted.
    |
    */
    'images' => [
        'max_size_kb' => (int) env('PRODUCT_IMPORT_IMAGE_MAX_SIZE_KB', 10240),
        'timeout' => (int) env('PRODUCT_IMPORT_IMAGE_TIMEOUT', 20),
        'per_job' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Staged rows, collected values, and the uploaded file are deleted
    | `retention_days` after an import finished, and `abandoned_days` after
    | an upload that was never imported. The import record, its statistics,
    | sheets, mappings, and AI proposals are kept.
    |
    */
    'retention_days' => (int) env('PRODUCT_IMPORT_RETENTION_DAYS', 30),

    'abandoned_days' => (int) env('PRODUCT_IMPORT_ABANDONED_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | Cron expression for `product-import:prune`. Set to null to not schedule
    | it.
    |
    */
    'schedule' => [
        'prune' => env('PRODUCT_IMPORT_PRUNE_SCHEDULE', '0 3 * * *'),
    ],
];
