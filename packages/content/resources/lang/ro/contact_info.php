<?php

return [
    'label' => 'Date de contact',
    'plural_label' => 'Date de contact',

    'edit' => [
        'label' => 'Editare date de contact',
    ],

    'sections' => [
        'content' => 'Contact',
        'address' => 'Adresă',
        'visibility' => 'Vizibilitate',
    ],

    'form' => [
        'intro' => [
            'label' => 'Text introductiv',
            'helper' => 'Paragraf opțional afișat deasupra detaliilor pe pagina de contact.',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'email' => [
            'label' => 'Email',
        ],
        'street' => [
            'label' => 'Stradă',
        ],
        'city' => [
            'label' => 'Oraș',
        ],
        'postal_code' => [
            'label' => 'Cod poștal',
        ],
        'country' => [
            'label' => 'Țară',
        ],
        'country_code' => [
            'label' => 'Cod țară',
            'helper' => 'ISO 3166-1 alpha-2 (ex. HU, RO). Folosit în markup-ul schema.',
        ],
        'is_active' => [
            'label' => 'Activ',
            'helper' => 'Când este inactiv, magazinul revine la config / detaliile hardcodate.',
        ],
    ],

    'table' => [
        'email' => [
            'label' => 'Email',
        ],
        'phone' => [
            'label' => 'Telefon',
        ],
        'is_active' => [
            'label' => 'Activ',
        ],
    ],
];
