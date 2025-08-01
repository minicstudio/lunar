<?php

return [

    'label' => 'Attribútum',

    'plural_label' => 'Attribútumok',

    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'description' => [
            'label' => 'Leírás',
        ],
        'handle' => [
            'label' => 'Azonosító',
        ],
        'type' => [
            'label' => 'Típus',
        ],
        'actions' => [
            'create' => [
                'label' => 'Új attribútum',
                'heading' => 'Attribútum létrehozása',
            ],
            'edit' => [
                'heading' => 'Attribútum szerkesztése',
            ],
            'delete' => [
                'heading' => 'Attribútum törlése',
                'bulk' => [
                    'heading' => 'Kiválasztott attribútumok törlése',
                ],
            ],
        ],
    ],

    'form' => [
        'attributable_type' => [
            'label' => 'Típus',
        ],
        'name' => [
            'label' => 'Név',
        ],
        'description' => [
            'label' => 'Leírás',
            'helper' => 'Segédszövegként jelenik meg a mező alatt',
        ],
        'handle' => [
            'label' => 'Azonosító',
        ],
        'searchable' => [
            'label' => 'Kereshető',
        ],
        'filterable' => [
            'label' => 'Szűrhető',
        ],
        'required' => [
            'label' => 'Kötelező',
        ],
        'type' => [
            'label' => 'Típus',
        ],
        'validation_rules' => [
            'label' => 'Validációs szabályok',
            'helper' => 'Attribútum mező szabályai, például: min:1|max:10|...',
        ],
    ],
];
