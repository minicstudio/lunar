<?php

return [

    'label' => 'Attribute',

    'plural_label' => 'Attributes',

    'table' => [
        'name' => [
            'label' => 'Name',
        ],
        'description' => [
            'label' => 'Description',
        ],
        'handle' => [
            'label' => 'Handle',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'actions' => [
            'create' => [
                'label' => 'New attribute',
                'heading' => 'Create attribute',
            ],
            'edit' => [
                'heading' => 'Edit attribute',
            ],
            'delete' => [
                'heading' => 'Delete attribute',
                'bulk' => [
                    'heading' => 'Delete selected attributes',
                ],  
            ],
        ],
    ],

    'form' => [
        'attributable_type' => [
            'label' => 'Type',
        ],
        'name' => [
            'label' => 'Name',
        ],
        'description' => [
            'label' => 'Description',
            'helper' => 'Use to display the helper text below the entry',
        ],
        'handle' => [
            'label' => 'Handle',
        ],
        'searchable' => [
            'label' => 'Searchable',
        ],
        'filterable' => [
            'label' => 'Filterable',
        ],
        'required' => [
            'label' => 'Required',
        ],
        'type' => [
            'label' => 'Type',
        ],
        'validation_rules' => [
            'label' => 'Validation Rules',
            'helper' => 'Rules for attribute field, example: min:1|max:10|...',
        ],
    ],
];
