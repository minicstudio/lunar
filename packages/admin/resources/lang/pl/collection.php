<?php

return [

    'label' => 'Kolekcja',

    'plural_label' => 'Kolekcje',

    'form' => [
        'name' => [
            'label' => 'Nazwa',
        ],
    ],

    'pages' => [
        'children' => [
            'label' => 'Podkolekcje',
            'actions' => [
                'create_child' => [
                    'label' => 'Dodaj podkolekcję',
                    'name' => [
                        'label' => 'Nazwa',
                    ],
                ],
            ],
            'table' => [
                'children_count' => [
                    'label' => 'Liczba podkolekcji',
                ],
                'name' => [
                    'label' => 'Nazwa',
                ],
            ],
        ],
        'edit' => [
            'label' => 'Podstawowe informacje',
            'actions' => [
                'delete' => [
                    'select' => 'Kolekcja docelowa',
                    'helper_text' => 'Wybierz kolekcję, do której mają zostać przeniesione elementy podrzędne tej kolekcji.'
                ],
            ]
        ],
        'media' => [
            'label' => 'Media',
        ],
        'products' => [
            'label' => 'Produkty',
            'actions' => [
                'attach' => [
                    'label' => 'Dołącz produkt',
                    'select' => 'Produkt',
                ],
                'detach' => [
                    'modal' => [
                        'heading' => 'Odłącz produkt',
                    ]
                ],
            ],
        ],
    ],
    'nested_set_item' => [
        'more_actions' => 'Więcej akcji',
    ],
];
