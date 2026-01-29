<?php

return [
    'shipping_methods' => [
        'customer_groups' => [
            'description' => 'Rendelje hozzá a vásárlói csoportokat ehhez a szállítási módhoz az elérhetőség meghatározásához.',
        ],
        'customer_types' => [
            'heading' => 'Ügyféltípus csatolása',
            'title' => 'Ügyféltípus',
            'description' => 'Csatolja az ügyféltípusokat (magán/jogi személy) ehhez a szállítási módhoz.',
        ],
    ],
    'shipping_rates' => [
        'title_plural' => 'Szállítási díjak',
        'actions' => [
            'create' => [
                'label' => 'Új szállítási díj létrehozása',
            ],
            'delete' => [
                'modal' => [
                    'heading' => 'Szállítási díj törlése',
                ],
            ],
            'edit' => [
                'modal' => [
                    'heading' => 'Szállítási díj szerkesztése',
                ],
            ],
        ],
        'notices' => [
            'prices_incl_tax' => 'Minden ár tartalmazza az adót, amelyet figyelembe veszünk a minimális vásárlási összeg kiszámításakor.',
            'prices_excl_tax' => 'Az árak nem tartalmazzák az adót, a minimális vásárlási összeg a kosár részösszegén alapul.',
        ],
        'form' => [
            'shipping_method_id' => [
                'label' => 'Szállítási mód',
            ],
            'price' => [
                'label' => 'Ár',
            ],
            'prices' => [
                'label' => 'Árlépcsők',
                'repeater' => [
                    'customer_group_id' => [
                        'label' => 'Vásárlói csoport',
                        'placeholder' => 'Bármely',
                    ],
                    'currency_id' => [
                        'label' => 'Pénznem',
                    ],
                    'min_spend' => [
                        'label' => 'Min. vásárlás',
                    ],
                    'min_weight' => [
                        'label' => 'Min. súly',
                        'helper_text' => 'Adja meg kg-ban',
                    ],
                    'max_weight' => [
                        'label' => 'Max. súly',
                        'helper_text' => 'Adja meg kg-ban',
                    ],
                    'price' => [
                        'label' => 'Ár',
                    ],
                ],
            ],
        ],
        'table' => [
            'shipping_method' => [
                'label' => 'Szállítási mód',
            ],
            'price' => [
                'label' => 'Ár',
            ],
            'price_breaks_count' => [
                'label' => 'Árlépcsők',
            ],
        ],
    ],
    'exclusions' => [
        'title_plural' => 'Szállítási kizárások',
        'form' => [
            'purchasable' => [
                'label' => 'Termék',
            ],
        ],
        'actions' => [
            'create' => [
                'label' => 'Új szállítási kizárás hozzáadása',
                'modal' => [
                    'heading' => 'Új szállítási kizárás hozzáadása',
                ],
            ],
            'delete' => [
                'modal' => [
                    'heading' => 'Szállítási kizárás törlése',
                ],
                'bulk' => [
                    'modal' => [
                        'heading' => 'Kijelölt szállítási kizárások törlése',
                    ],
                ],
            ],
            'edit' => [
                'modal' => [
                    'heading' => 'Szállítási kizárás szerkesztése',
                ],
            ],
            'attach' => [
                'label' => 'Kizárási lista hozzáadása',
                'modal' => [
                    'heading' => 'Kizárási lista csatolása',
                ],
            ],
            'detach' => [
                'label' => 'Eltávolítás',
                'modal' => [
                    'heading' => 'Kizárási lista leválasztása',
                ],
            ],
        ],
    ],
];
