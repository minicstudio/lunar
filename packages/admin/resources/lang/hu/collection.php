<?php

return [

    'label' => 'Gyűjtemény',

    'plural_label' => 'Gyűjtemények',

    'form' => [
        'name' => [
            'label' => 'Név',
        ],
    ],

    'pages' => [
        'children' => [
            'label' => 'Gyermek gyűjtemények',
            'actions' => [
                'create_child' => [
                    'label' => 'Gyermek gyűjtemény létrehozása',
                ],
            ],
            'table' => [
                'children_count' => [
                    'label' => 'Gyermekek száma',
                ],
                'name' => [
                    'label' => 'Név',
                ],
            ],
        ],
        'edit' => [
            'label' => 'Alapvető információk',
        ],
        'products' => [
            'label' => 'Termékek',
            'actions' => [
                'attach' => [
                    'label' => 'Termék társítása',
                ],
            ],
        ],
    ],

];
