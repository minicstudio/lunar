<?php

return [
    'label' => 'Category',
    'plural_label' => 'Categories',

    'edit' => [
        'label' => 'Basic Information',
    ],

    'form' => [
        'name' => [
            'label' => 'Name',
        ],
        'handle' => [
            'label' => 'Handle',
        ],
        'status' => [
            'label' => 'Status',
            'options' => [
                'published' => [
                    'label' => 'Published',
                    'description' => 'This category will be available across all enabled channels',
                ],
                'draft' => [
                    'label' => 'Draft',
                    'description' => 'This category will be hidden across all channels',
                ],
            ],
        ],
    ],

    'table' => [
        'name' => [
            'label' => 'Name',
        ],
        'status' => [
            'label' => 'Status',
            'states' => [
                'draft' => 'Draft',
                'published' => 'Published',
            ],
        ],
    ],

    'actions' => [
        'edit_status' => [
            'label' => 'Update Status',
            'heading' => 'Update Status',
        ],
    ],

    'pages' => [
        'availability' => [
            'label' => 'Availability',
        ],
    ],

    'filters' => [
        'status' => [
            'label' => 'Status',
            'placeholder' => 'Select status',
            'options' => [
                'draft' => 'Draft',
                'published' => 'Published',
            ],
        ],
    ],
];
