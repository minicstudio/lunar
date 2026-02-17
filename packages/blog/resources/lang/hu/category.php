<?php

return [
    'label' => 'Kategória',
    'plural_label' => 'Kategóriák',

    'edit' => [
        'label' => 'Alapvető információk',
    ],

    'form' => [
        'name' => [
            'label' => 'Név',
        ],
        'handle' => [
            'label' => 'Azonosító',
        ],
        'status' => [
            'label' => 'Állapot',
            'options' => [
                'published' => [
                    'label' => 'Közzétéve',
                    'description' => 'Ez a kategória minden engedélyezett csatornán elérhető lesz',
                ],
                'draft' => [
                    'label' => 'Piszkozat',
                    'description' => 'Ez a kategória minden csatornán rejtett lesz',
                ],
            ],
        ],
    ],

    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'status' => [
            'label' => 'Állapot',
            'states' => [
                'draft' => 'Piszkozat',
                'published' => 'Közzétéve',
            ],
        ],
    ],

    'actions' => [
        'edit_status' => [
            'label' => 'Állapot frissítése',
            'heading' => 'Állapot frissítése',
        ],
    ],

    'pages' => [
        'availability' => [
            'label' => 'Elérhetőség',
        ],
    ],

    'filters' => [
        'status' => [
            'label' => 'Állapot',
            'placeholder' => 'Válassz állapotot',
            'options' => [
                'draft' => 'Piszkozat',
                'published' => 'Közzétéve',
            ],
        ],
    ],
];
