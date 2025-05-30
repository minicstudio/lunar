<?php

return [

    'label' => 'Ügyfélcsoport',

    'plural_label' => 'Ügyfélcsoportok',

    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'handle' => [
            'label' => 'Azonosító',
        ],
        'default' => [
            'label' => 'Alapértelmezett',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Név',
        ],
        'handle' => [
            'label' => 'Azonosító',
        ],
        'default' => [
            'label' => 'Alapértelmezett',
        ],
    ],

    'action' => [
        'delete' => [
            'notification' => [
                'error_protected' => 'Ezt az ügyfélcsoportot nem lehet törölni, mert hozzá vannak rendelve ügyfelek.',
            ],
        ],
    ],
];
