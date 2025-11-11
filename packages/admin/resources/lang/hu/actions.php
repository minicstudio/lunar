<?php

return [
    'collections' => [
        'create_root' => [
            'label' => 'Gyökér gyűjtemény létrehozása',
            'form' => [
                'name' => [
                    'label' => 'Név',
                ],
            ],
        ],
        'create_child' => [
            'label' => 'Gyermek gyűjtemény létrehozása',
            'form' => [
                'name' => [
                    'label' => 'Név',
                ],
            ],
        ],
        'move' => [
            'label' => 'Gyűjtemény áthelyezése',
        ],
        'delete' => [
            'label' => 'Törlés',
        ],
    ],
    'orders' => [
        'update_status' => [
            'label' => 'Státusz frissítése',
            'wizard' => [
                'step_one' => [
                    'label' => 'Státusz',
                ],
                'step_two' => [
                    'label' => 'Levelek és értesítések',
                    'no_mailers' => 'Ehhez a státuszhoz nem érhető el levélküldő.',
                ],
                'step_three' => [
                    'label' => 'Előnézet és mentés',
                    'no_mailers' => 'Nem lett kiválasztva levélküldő az előnézethez.',
                ],
            ],
            'notification' => [
                'label' => 'A rendelés státusza frissítve lett',
            ],
            'billing_email' => [
                'label' => 'Számlázási e-mail',
            ],
            'shipping_email' => [
                'label' => 'Szállítási e-mail',
            ],
        ],
    ],
];
