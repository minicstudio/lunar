<?php

return [
    'tags' => [
        'notification' => [
            'updated' => 'Címkék frissítve',
        ],
    ],

    'activity-log' => [

        'input' => [
            'placeholder' => 'Megjegyzés hozzáadása',
        ],

        'action' => [
            'add-comment' => 'Megjegyzés hozzáadása',
        ],

        'system' => 'Rendszer',

        'partials' => [
            'orders' => [
                'order_created' => 'Rendelés létrehozva',

                'status_change' => 'Státusz frissítve',

                'capture' => 'Fizetés: :amount, kártya utolsó négy számjegye: :last_four',

                'authorized' => 'Engedélyezve: :amount, kártya utolsó négy számjegye: :last_four',

                'refund' => 'Visszatérítés: :amount, kártya utolsó négy számjegye: :last_four',

                'address' => ':type frissítve',

                'billingAddress' => 'Számlázási cím',

                'shippingAddress' => 'Szállítási cím',
            ],

            'update' => [
                'updated' => ':model frissítve',
            ],

            'create' => [
                'created' => ':model létrehozva',
            ],

            'tags' => [
                'updated' => 'Címkék frissítve',
                'added' => 'Hozzáadva',
                'removed' => 'Eltávolítva',
            ],
        ],

        'notification' => [
            'comment_added' => 'Megjegyzés hozzáadva',
        ],

    ],

    'forms' => [
        'youtube' => [
            'helperText' => 'Add meg a YouTube videó azonosítóját. pl.: dQw4w9WgXcQ',
        ],
    ],

    'collection-tree-view' => [
        'actions' => [
            'move' => [
                'form' => [
                    'target_id' => [
                        'label' => 'Szülő gyűjtemény',
                    ],
                ],
            ],
            'make_root' => [
                'label' => 'Gyökérré tétel',
            ],
            'delete' => [
                'modal' => [
                    'heading' => 'Gyűjtemény törlése',
                ],
            ]
        ],
        'notifications' => [
            'collections-reordered' => [
                'success' => 'Gyűjtemények átrendezve',
            ],
            'node-expanded' => [
                'danger' => 'Nem sikerült betölteni a gyűjteményeket',
            ],
            'delete' => [
                'danger' => 'Nem sikerült törölni a gyűjteményt',
            ],
        ],
    ],

    'product-options-list' => [
        'add-option' => [
            'label' => 'Opció hozzáadása',
        ],
        'delete-option' => [
            'label' => 'Opció törlése',
        ],
        'remove-shared-option' => [
            'label' => 'Megosztott opció eltávolítása',
        ],
        'add-value' => [
            'label' => 'Új érték hozzáadása',
        ],
        'name' => [
            'label' => 'Név',
        ],
        'values' => [
            'label' => 'Értékek',
        ],
    ],
];
