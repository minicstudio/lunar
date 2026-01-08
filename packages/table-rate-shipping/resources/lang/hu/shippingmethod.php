<?php

return [
    'label_plural' => 'Szállítási módok',
    'label' => 'Szállítási mód',
    'form' => [
        'name' => [
            'label' => 'Név',
        ],
        'description' => [
            'label' => 'Leírás',
        ],
        'code' => [
            'label' => 'Kód',
        ],
        'cutoff' => [
            'label' => 'Leadási határidő',
        ],
        'charge_by' => [
            'label' => 'Díjszabás alapja',
            'options' => [
                'cart_total' => 'Kosár értéke',
                'weight' => 'Súly',
            ],
        ],
        'driver' => [
            'label' => 'Típus',
            'options' => [
                'ship-by' => 'Normál',
                'collection' => 'Személyes átvétel',
            ],
        ],
        'stock_available' => [
            'label' => 'A kosárban lévő összes terméknek raktáron kell lennie',
        ],
        'limitations' => [
            'label' => 'Korlátozások',
        ],
    ],
    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'code' => [
            'label' => 'Kód',
        ],
        'driver' => [
            'label' => 'Típus',
            'options' => [
                'ship-by' => 'Normál',
                'collection' => 'Személyes átvétel',
            ],
        ],
    ],
    'pages' => [
        'availability' => [
            'label' => 'Elérhetőség',
            'customer_groups' => 'Ez a szállítási mód jelenleg egyetlen vásárlói csoport számára sem elérhető.',
        ],
        'edit' => [
            'navigation_label' => 'Szállítási mód szerkesztése',
        ],
    ],
];
