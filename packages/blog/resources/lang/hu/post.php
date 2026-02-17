<?php

return [
    'label' => 'Bejegyzés',
    'plural_label' => 'Bejegyzések',

    'edit' => [
        'label' => 'Alapvető információk',
    ],

    'form' => [
        'title' => [
            'label' => 'Cím',
        ],
        'handle' => [
            'label' => 'Azonosító',
        ],
        'status' => [
            'label' => 'Állapot',
            'options' => [
                'published' => [
                    'label' => 'Közzétéve',
                    'description' => 'Ez a bejegyzés minden engedélyezett csatornán elérhető lesz',
                ],
                'draft' => [
                    'label' => 'Piszkozat',
                    'description' => 'Ez a bejegyzés minden csatornán rejtett lesz',
                ],
            ],
        ],
        'categories' => [
            'title' => [
                'label' => 'Kategóriák',
            ],
        ],
    ],

    'section' => [
        'categories' => [
            'title' => 'Blog kategóriák',
            'description' => 'Kezelje a blogbejegyzés kategóriáit. Válasszon ki egy meglévő kategóriát, vagy hozzon létre egy újat, ha szükséges.',
            'action' => [
                'label' => 'Új kategória',
                'modal' => [
                    'heading' => 'Kategória létrehozása',
                    'submit' => 'Létrehozás',
                ],
                'notification' => [
                    'title' => 'Létrehozott kategória',
                    'body' => 'A kategóriát sikeresen létrehoztuk.',
                ],
            ],
        ],
    ],

    'table' => [
        'title' => [
            'label' => 'Cím',
        ],
        'status' => [
            'label' => 'Állapot',
            'states' => [
                'draft' => 'Piszkozat',
                'published' => 'Közzétéve',
            ],
        ],
        'author' => [
            'label' => 'Szerző',
        ],
    ],

    'actions' => [
        'edit_status' => [
            'label' => 'Állapot frissítése',
            'heading' => 'Állapot frissítése',
        ],
        'preview' => [
            'label' => 'Előnézet',
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
        'author' => [
            'label' => 'Szerző',
            'placeholder' => 'Válassz szerzőt',
        ],
    ],
];
