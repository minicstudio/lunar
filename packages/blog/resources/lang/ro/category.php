<?php

return [
    'label' => 'Categorie',
    'plural_label' => 'Categorii',

    'edit' => [
        'label' => 'Informații de bază',
    ],

    'form' => [
        'name' => [
            'label' => 'Nume',
        ],
        'handle' => [
            'label' => 'Identificator',
        ],
        'status' => [
            'label' => 'Status',
            'options' => [
                'published' => [
                    'label' => 'Publicat',
                    'description' => 'Această categorie va fi disponibilă pe toate canalele activate',
                ],
                'draft' => [
                    'label' => 'Ciornă',
                    'description' => 'Această categorie va fi ascunsă pe toate canalele',
                ],
            ],
        ],
    ],

    'table' => [
        'name' => [
            'label' => 'Nume',
        ],
        'status' => [
            'label' => 'Status',
            'states' => [
                'draft' => 'Ciornă',
                'published' => 'Publicat',
            ],
        ],
    ],

    'actions' => [
        'edit_status' => [
            'label' => 'Actualizare Status',
            'heading' => 'Actualizare Status',
        ],
    ],

    'pages' => [
        'availability' => [
            'label' => 'Disponibilitate',
        ],
    ],

    'filters' => [
        'status' => [
            'label' => 'Status',
            'placeholder' => 'Selectează status',
            'options' => [
                'draft' => 'Ciornă',
                'published' => 'Publicat',
            ],
        ],
    ],
];
