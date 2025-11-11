<?php

return [
    'dropdown' => [
        'label' => 'Legördülő',
        'form' => [
            'lookups' => [
                'label' => 'Lekérdezések',
                'key_label' => 'Címke',
                'value_label' => 'Érték',
            ],
        ],
    ],
    'listfield' => [
        'label' => 'Lista mező',
    ],
    'text' => [
        'label' => 'Szöveg',
        'form' => [
            'richtext' => [
                'label' => 'Formázott szöveg',
            ],
        ],
    ],
    'translatedtext' => [
        'label' => 'Fordított szöveg',
        'form' => [
            'richtext' => [
                'label' => 'Formázott szöveg',
            ],
            'locales' => 'Nyelvek',
        ],
    ],
    'toggle' => [
        'label' => 'Kapcsoló',
    ],
    'youtube' => [
        'label' => 'YouTube',
    ],
    'vimeo' => [
        'label' => 'Vimeo',
    ],
    'number' => [
        'label' => 'Szám',
        'form' => [
            'min' => [
                'label' => 'Minimum',
            ],
            'max' => [
                'label' => 'Maximum',
            ],
        ],
    ],
    'file' => [
        'label' => 'Fájl',
        'form' => [
            'file_types' => [
                'label' => 'Engedélyezett fájltípusok',
                'placeholder' => 'Új MIME',
            ],
            'multiple' => [
                'label' => 'Több fájl engedélyezése',
            ],
            'min_files' => [
                'label' => 'Minimális fájlok',
            ],
            'max_files' => [
                'label' => 'Maximális fájlok',
            ],
        ],
    ],
];
