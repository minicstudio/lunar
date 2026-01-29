<?php

return [
    'collections' => [
        'create_root' => [
            'label' => 'Gyökérgyűjtemény létrehozása',
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
            'notifications' => [
                'cannot_delete' => [
                    'title' => 'Nem törölhető',
                    'body' => 'Ennek a gyűjteménynek vannak algyűjteményei, ezért nem törölhető.',
                ],
            ],
        ],
    ],
    'orders' => [
        'update_status' => [
            'label' => 'Állapot frissítése',
            'wizard' => [
                'step_one' => [
                    'label' => 'Állapot',
                ],
                'step_two' => [
                    'label' => 'E-mailek és értesítések',
                    'no_mailers' => 'Ehhez az állapothoz nincs elérhető e-mail sablon.',
                ],
                'step_three' => [
                    'label' => 'Előnézet és mentés',
                    'no_mailers' => 'Nincs kiválasztva e-mail előnézethez.',
                ],
            ],
            'notification' => [
                'label' => 'A rendelés állapota frissítve',
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
