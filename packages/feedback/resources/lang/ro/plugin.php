<?php

return [
    'label' => 'Feedback',
    'plural_label' => 'Feedback-uri',

    'table' => [
        'type' => [
            'label' => 'Tip',
        ],
        'score' => [
            'label' => 'Scor',
        ],
        'order_reference' => [
            'label' => 'Referință comandă',
        ],
        'comment' => [
            'label' => 'Comentariu',
        ],
        'locale' => [
            'label' => 'Limbă',
        ],
        'created_at' => [
            'label' => 'Trimis la',
        ],
    ],

    'filters' => [
        'type' => [
            'label' => 'Tip',
        ],
        'score' => [
            'label' => 'Scor',
        ],
    ],

    'order' => [
        'score' => 'Scor feedback',
        'comment' => 'Detalii feedback',
    ],
];
