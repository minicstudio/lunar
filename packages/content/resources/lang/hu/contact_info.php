<?php

return [
    'label' => 'Kapcsolat adatok',
    'plural_label' => 'Kapcsolat adatok',

    'edit' => [
        'label' => 'Kapcsolat adatok szerkesztése',
    ],

    'sections' => [
        'content' => 'Kapcsolat',
        'address' => 'Cím',
        'visibility' => 'Láthatóság',
    ],

    'form' => [
        'intro' => [
            'label' => 'Bevezető szöveg',
            'helper' => 'Opcionális bekezdés a kapcsolatoldalon a részletek felett.',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'email' => [
            'label' => 'E-mail',
        ],
        'street' => [
            'label' => 'Utca, házszám',
        ],
        'city' => [
            'label' => 'Város',
        ],
        'postal_code' => [
            'label' => 'Irányítószám',
        ],
        'country' => [
            'label' => 'Ország',
        ],
        'country_code' => [
            'label' => 'Országkód',
            'helper' => 'ISO 3166-1 alpha-2 (pl. HU, RO). A sémamarkoláshoz használjuk.',
        ],
        'is_active' => [
            'label' => 'Aktív',
            'helper' => 'Inaktív állapotban a bolt a config / hardkódolt kapcsolatadatokra esik vissza.',
        ],
    ],

    'table' => [
        'email' => [
            'label' => 'E-mail',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'is_active' => [
            'label' => 'Aktív',
        ],
    ],
];
