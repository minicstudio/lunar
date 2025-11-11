<?php

return [

    'label' => 'Collectie',

    'plural_label' => 'Collecties',

    'form' => [
        'name' => [
            'label' => 'Naam',
        ],
    ],

    'pages' => [
        'children' => [
            'label' => 'Subcollecties',
            'actions' => [
                'create_child' => [
                    'label' => 'Maak Subcollectie',
                    'name' => [
                        'label' => 'Naam',
                    ],
                ],
            ],
            'table' => [
                'children_count' => [
                    'label' => 'Aantal Kinderen',
                ],
                'name' => [
                    'label' => 'Naam',
                ],
            ],
        ],
        'edit' => [
            'label' => 'Basisinformatie',
            'actions' => [
                'delete' => [
                    'select' => 'Doelcollectie',
                    'helper_text' => 'Kies naar welke collectie de onderliggende items van deze collectie moeten worden overgezet.'
                ],
            ]
        ],
        'products' => [
            'label' => 'Producten',
            'actions' => [
                'attach' => [
                    'label' => 'Product Toevoegen',
                    'select' => 'Product',
                ],
                'detach' => [
                    'modal' => [
                        'heading' => 'Product losmaken',
                    ]
                ],
            ],
        ],
    ],
    'nested_set_item' => [
        'more_actions' => 'Meer acties',
    ],
];
