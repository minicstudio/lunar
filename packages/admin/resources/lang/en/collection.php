<?php

return [

    'label' => 'Collection',

    'plural_label' => 'Collections',

    'form' => [
        'name' => [
            'label' => 'Name',
        ],
    ],

    'pages' => [
        'children' => [
            'label' => 'Child Collections',
            'actions' => [
                'create_child' => [
                    'label' => 'Create Child Collection',
                    'name' => [
                        'label' => 'Name',
                    ],
                ],
            ],
            'table' => [
                'children_count' => [
                    'label' => 'No. Children',
                ],
                'name' => [
                    'label' => 'Name',
                ],
            ],
        ],
        'edit' => [
            'label' => 'Basic Information',
            'actions' => [
                'delete' => [
                    'select' => 'Target collection',
                    'helper_text' => 'Choose which collection the children of this collection should be transferred to.'
                ],
            ]
        ],
        'products' => [
            'label' => 'Products',
            'actions' => [
                'attach' => [
                    'label' => 'Attach Product',
                    'select' => 'Product',
                ],
                'detach' => [
                    'modal' => [
                        'heading' => 'Detach Product',
                    ]
                ],
            ],
        ],
    ],
    'nested_set_item' => [
        'more_actions' => 'More Actions',
    ]
];
