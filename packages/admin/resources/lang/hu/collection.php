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
                    'name' => [
                        'label' => 'Név',
                    ],
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
            'actions' => [
                'delete' => [
                    'select' => 'Célgyűjtemény',
                    'helper_text' => 'Válassza ki, hogy a gyűjtemény gyermekei melyik gyűjteménybe legyenek áthelyezve.'
                ],
            ]
        ],
        'products' => [
            'label' => 'Termékek',
            'actions' => [
                'attach' => [
                    'label' => 'Termék társítása',
                    'select' => 'Termék',
                ],
                'detach' => [
                    'modal' => [
                        'heading' => 'Termék leválasztása',
                    ]
                ],
            ],
        ],
    ],
    'nested_set_item' => [
        'more_actions' => 'További műveletek',
    ],
];
