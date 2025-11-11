<?php

return [
    'shipping_methods' => [
        'customer_groups' => [
            'description' => 'Rendelje hozzá az ügyfélcsoportokat ehhez a szállítási módhoz, hogy meghatározza annak elérhetőségét.',
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
                'label' => 'Árkategóriák',
                'repeater' => [
                    'customer_group_id' => [
                        'label' => 'Ügyfélcsoport',
                        'placeholder' => 'Bármelyik',
                    ],
                    'currency_id' => [
                        'label' => 'Pénznem',
                    ],
                    'min_quantity' => [
                        'label' => 'Min. vásárlás',
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
                'label' => 'Árkategóriák',
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
