<?php

return [
    'label' => 'Szállítási zóna',
    'label_plural' => 'Szállítási zónák',
    'form' => [
        'unrestricted' => [
            'content' => 'Ez a szállítási zóna nem tartalmaz korlátozásokat, és minden vásárló számára elérhető lesz a pénztárnál.',
        ],
        'name' => [
            'label' => 'Név',
        ],
        'type' => [
            'label' => 'Típus',
            'options' => [
                'unrestricted' => 'Korlátozás nélküli',
                'countries' => 'Országokra korlátozva',
                'states' => 'Államokra / tartományokra korlátozva',
                'postcodes' => 'Irányítószámokra korlátozva',
            ],
        ],
        'country' => [
            'label' => 'Ország',
        ],
        'states' => [
            'label' => 'Államok',
        ],
        'countries' => [
            'label' => 'Államok',
        ],
        'postcodes' => [
            'label' => 'Irányítószámok',
            'helper' => 'Adj meg minden irányítószámot új sorba. Támogatja a helyettesítő karaktereket, pl.: NW*',
        ],
    ],
    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'type' => [
            'label' => 'Típus',
            'options' => [
                'unrestricted' => 'Korlátozás nélküli',
                'countries' => 'Országokra korlátozva',
                'states' => 'Államokra / tartományokra korlátozva',
                'postcodes' => 'Irányítószámokra korlátozva',
            ],
        ],
    ],
    'pages' => [
        'edit' => [
            'navigation_label' => 'Szállítási zóna szerkesztése',
        ],
    ],
];
