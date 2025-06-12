<?php

return [

    'label' => 'Márka',

    'plural_label' => 'Márkák',

    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'products_count' => [
            'label' => 'Termékek száma',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Név',
        ],
    ],

    'action' => [
        'delete' => [
            'notification' => [
                'error_protected' => 'Ez a márka nem törölhető, mivel termékek kapcsolódnak hozzá.',
            ],
        ],
    ],

    'pages' => [
        'products' => [
            'label' => 'Termékek',
            'actions' => [
                'attach' => [
                    'label' => 'Termék társítása',
                    'modal' => [
                        'heading' => 'Termék társítása',
                    ],
                    'form' => [
                        'record_id' => [
                            'label' => 'Termék',
                        ],
                    ],
                    'notification' => [
                        'success' => 'A termék sikeresen társítva lett a márkához.',
                    ],
                ],
                'detach' => [
                    'notification' => [
                        'success' => 'A termék le lett választva.',
                    ],
                    'modal' => [
                        'heading' => 'Termék leválasztása',
                    ],
                ],
            ],
        ],
        'collections' => [
            'label' => 'Gyűjtemények',
            'table' => [
                'header_actions' => [
                    'attach' => [
                        'record_select' => [
                            'placeholder' => 'Válassz egy gyűjteményt',
                        ],
                    ],
                ],
            ],
            'actions' => [
                'attach' => [
                    'label' => 'Gyűjtemény társítása',
                    'modal' => [
                        'heading' => 'Gyűjtemény csatolása',
                    ],
                ],
                'detach' => [
                    'modal' => [
                        'heading' => 'Gyűjtemény leválasztása',
                    ],
                ],
            ],
        ],
    ],

];
