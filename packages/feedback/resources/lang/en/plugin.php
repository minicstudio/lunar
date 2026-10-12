<?php

return [
    'label' => 'Feedback',
    'plural_label' => 'Feedback',

    'table' => [
        'type' => [
            'label' => 'Type',
        ],
        'score' => [
            'label' => 'Score',
        ],
        'order_reference' => [
            'label' => 'Order Reference',
        ],
        'comment' => [
            'label' => 'Comment',
        ],
        'locale' => [
            'label' => 'Locale',
        ],
        'created_at' => [
            'label' => 'Submitted At',
        ],
    ],

    'filters' => [
        'type' => [
            'label' => 'Type',
        ],
        'score' => [
            'label' => 'Score',
        ],
    ],

    'order' => [
        'score' => 'Feedback score',
        'comment' => 'Feedback details',
    ],
];
