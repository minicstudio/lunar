<?php

return [
    'label' => 'FAQ Item',
    'plural_label' => 'FAQ Items',

    'edit' => [
        'label' => 'Edit FAQ Item',
    ],

    'sections' => [
        'content' => 'Question & Answer',
        'visibility' => 'Visibility',
    ],

    'form' => [
        'question' => [
            'label' => 'Question',
        ],
        'answer' => [
            'label' => 'Answer',
            'helper' => 'Supports basic formatting (lists, bold, links).',
        ],
        'is_active' => [
            'label' => 'Active',
        ],
    ],

    'table' => [
        'question' => [
            'label' => 'Question',
        ],
        'is_active' => [
            'label' => 'Active',
        ],
    ],
];
