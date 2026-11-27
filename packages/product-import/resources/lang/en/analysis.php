<?php

return [
    'ai_line' => [
        'configured' => 'AI analysis: :provider · :model',
        'default_model' => 'default model',
        'none' => 'AI analysis is not configured.',
    ],
    'failures' => [
        'not_configured' => 'AI analysis is not configured. The provider or API key is missing. Contact your developer.',
        'auth_failed' => 'The AI provider rejected the API key. The configuration needs to be checked.',
        'rate_limited' => 'The AI quota is used up or there are too many requests. Try again in a few minutes.',
        'provider_timeout' => 'The AI provider did not respond. This is usually temporary. Try again.',
        'provider_error' => 'The AI provider answered with an error. This is usually temporary. Try again.',
        'invalid_response' => 'The AI answer could not be used: :reason. Try again.',
        'no_product_data' => 'The AI found no column with a product name or a SKU. Check that the sheet holds products, or try again.',
    ],
    'reasons' => [
        'no_columns' => 'the answer has no column list',
        'unknown_column' => 'column :index does not exist',
        'unknown_target' => 'the field ":target" is not allowed',
        'unknown_language' => 'the language ":locale" is not a store language',
        'duplicate_target' => 'two columns were marked as :target',
        'unknown_grouping' => 'the grouping is not one of the allowed ones',
        'missing_group_column' => 'the grouping column does not exist',
        'no_product_data' => 'no column holds a product name or a SKU',
        'missing_pair' => 'the column ":column" has no pair number',
        'pair_without_value' => 'pair :pair has an option name column but no option value column',
        'missing_parent_column' => 'grouping by parent needs a parent reference column',
    ],
];
