<?php

return [
    'plural_label' => 'Kedvezmények',
    'label' => 'Kedvezmény',
    'form' => [
        'conditions' => [
            'heading' => 'Feltételek',
        ],
        'buy_x_get_y' => [
            'heading' => 'Vásárolj X-et, kapj Y-t',
        ],
        'amount_off' => [
            'heading' => 'Kedvezmény összege',
        ],
        'name' => [
            'label' => 'Név',
        ],
        'handle' => [
            'label' => 'Azonosító',
        ],
        'starts_at' => [
            'label' => 'Kezdési dátum',
        ],
        'ends_at' => [
            'label' => 'Befejezési dátum',
        ],
        'priority' => [
            'label' => 'Prioritás',
            'helper_text' => 'A magasabb prioritású kedvezmények előbb kerülnek alkalmazásra.',
            'options' => [
                'low' => [
                    'label' => 'Alacsony',
                ],
                'medium' => [
                    'label' => 'Közepes',
                ],
                'high' => [
                    'label' => 'Magas',
                ],
            ],
        ],
        'stop' => [
            'label' => 'Más kedvezmények alkalmazásának megállítása ez után',
        ],
        'coupon' => [
            'label' => 'Kupon',
            'helper_text' => 'Adja meg a kuponkódot, amely szükséges a kedvezmény érvényesítéséhez. Ha üresen hagyja, automatikusan érvényesül.',
        ],
        'max_uses' => [
            'label' => 'Maximális felhasználás',
            'helper_text' => 'Hagyja üresen a korlátlan felhasználáshoz.',
        ],
        'max_uses_per_user' => [
            'label' => 'Maximális felhasználás felhasználónként',
            'helper_text' => 'Hagyja üresen a korlátlan felhasználáshoz.',
        ],
        'minimum_cart_amount' => [
            'label' => 'Minimum kosárérték',
        ],
        'min_qty' => [
            'label' => 'Termékmennyiség',
            'helper_text' => 'Adja meg, hogy hány jogosult termék szükséges a kedvezmény érvényesítéséhez.',
        ],
        'reward_qty' => [
            'label' => 'Ingyenes termékek száma',
            'helper_text' => 'Hány terméket kedvezményesítenek.',
        ],
        'max_reward_qty' => [
            'label' => 'Maximális kedvezményes mennyiség',
            'helper_text' => 'A maximális kedvezményesíthető termékmennyiség a feltételektől függetlenül.',
        ],
        'automatic_rewards' => [
            'label' => 'Jutalmak automatikus hozzáadása',
            'helper_text' => 'Kapcsolja be, hogy a jutalomtermékek automatikusan hozzáadódjanak, ha nem szerepelnek a kosárban.',
        ],
        'type' => [
            'label' => 'Típus',
            'options' => [
                'buy_x_get_y' => [
                    'label' => 'Vásárolj X-et, kapj Y-t',
                ],
                'amount_off' => [
                    'label' => 'Kedvezmény összege',
                ],
            ],
        ],
        'fixed_value' => [
            'label' => 'Fix összeg',
        ],
        'percentage' => [
            'label' => 'Százalék',
        ],
    ],
    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'status' => [
            'label' => 'Állapot',
            \Lunar\Models\Discount::ACTIVE => [
                'label' => 'Aktív',
            ],
            \Lunar\Models\Discount::PENDING => [
                'label' => 'Függőben',
            ],
            \Lunar\Models\Discount::EXPIRED => [
                'label' => 'Lejárt',
            ],
            \Lunar\Models\Discount::SCHEDULED => [
                'label' => 'Ütemezett',
            ],
        ],
        'type' => [
            'label' => 'Típus',
        ],
        'starts_at' => [
            'label' => 'Kezdési dátum',
        ],
        'ends_at' => [
            'label' => 'Befejezési dátum',
        ],
    ],
    'pages' => [
        'availability' => [
            'label' => 'Elérhetőség',
        ],
        'limitations' => [
            'label' => 'Korlátozások',
        ],
        'edit' => [
            'navigation_label' => 'Kedvezmény szerkesztése',
        ]
    ],
    'relationmanagers' => [
        'collections' => [
            'title' => 'Gyűjtemények',
            'description' => 'Válassza ki, mely gyűjteményekre vonatkozzon ez a kedvezmény.',
            'actions' => [
                'attach' => [
                    'label' => 'Gyűjtemény csatolása',
                ],
                'detach' => [
                    'label' => 'Gyűjtemény leválasztása',
                    'bulk' => [
                        'label' => 'Kiválasztott gyűjtemények leválasztása',
                    ],
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Név',
                ],
                'type' => [
                    'label' => 'Típus',
                    'limitation' => [
                        'label' => 'Korlátozás',
                    ],
                    'exclusion' => [
                        'label' => 'Kizárás',
                    ],
                ],
            ],
            'form' => [
                'type' => [
                    'label' => 'Típus',
                    'options' => [
                        'limitation' => [
                            'label' => 'Korlátozás',
                        ],
                        'exclusion' => [
                            'label' => 'Kizárás',
                        ],
                    ],
                ],
            ],
        ],
        'customers' => [
            'title' => 'Ügyfelek',
            'description' => 'Válassza ki, mely ügyfelekre vonatkozzon ez a kedvezmény.',
            'actions' => [
                'attach' => [
                    'label' => 'Ügyfél csatolása',
                ],
                'detach' => [
                    'label' => 'Ügyfél leválasztása',
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Név',
                ],
            ],
        ],
        'brands' => [
            'title' => 'Márkák',
            'description' => 'Válassza ki, mely márkákra vonatkozzon ez a kedvezmény.',
            'actions' => [
                'attach' => [
                    'label' => 'Márka csatolása',
                ],
                'detach' => [
                    'heading' => 'Márka leválasztása',
                    'bulk' => [
                        'heading' => 'Kiválasztott márkák leválasztása',
                    ],
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Név',
                ],
                'type' => [
                    'label' => 'Típus',
                    'limitation' => [
                        'label' => 'Korlátozás',
                    ],
                    'exclusion' => [
                        'label' => 'Kizárás',
                    ],
                ],
            ],
            'form' => [
                'type' => [
                    'label' => 'Típus',
                    'options' => [
                        'limitation' => [
                            'label' => 'Korlátozás',
                        ],
                        'exclusion' => [
                            'label' => 'Kizárás',
                        ],
                    ],
                ],
            ],
        ],
        'products' => [
            'title' => 'Termékek',
            'description' => 'Válassza ki, mely termékekre vonatkozzon ez a kedvezmény.',
            'actions' => [
                'attach' => [
                    'label' => 'Termék hozzáadása',
                    'modal' => [
                        'heading' => 'Kedvezményes termék létrehozása',
                    ],
                ],
                'delete' => [
                    'heading' => 'Kedvezményes tétel törlése',
                    'bulk' => [
                        'heading' => 'Kiválasztott kedvezményes tételek törlése',
                    ],
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Név',
                ],
                'type' => [
                    'label' => 'Típus',
                    'limitation' => [
                        'label' => 'Korlátozás',
                    ],
                    'exclusion' => [
                        'label' => 'Kizárás',
                    ],
                ],
            ],
            'form' => [
                'type' => [
                    'options' => [
                        'limitation' => [
                            'label' => 'Korlátozás',
                        ],
                        'exclusion' => [
                            'label' => 'Kizárás',
                        ],
                    ],
                ],
                'purchasable' => [
                    'label' => 'Vásárolható tétel',
                    'types' => [
                        'product' => [
                            'label' => 'Termék',
                        ],
                    ],
                ],
            ],
        ],
        'rewards' => [
            'title' => 'Termékjutalmak',
            'description' => 'Válassza ki, mely termékek lesznek kedvezményesek, ha a kosárban vannak és a fenti feltételek teljesülnek.',
            'actions' => [
                'attach' => [
                    'label' => 'Termék hozzáadása',
                    'modal' => [
                        'heading' => 'Kedvezményes tétel létrehozása',
                    ],
                ],
                'delete' => [
                    'modal' => [
                        'heading' => 'Kedvezményes tétel törlése',
                        'bulk' => [
                            'heading' => 'Kiválasztott kedvezményes tételek törlése',
                        ],
                    ]
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Név',
                ],
                'type' => [
                    'label' => 'Típus',
                    'limitation' => [
                        'label' => 'Korlátozás',
                    ],
                    'exclusion' => [
                        'label' => 'Kizárás',
                    ],
                ],
            ],
            'form' => [
                'type' => [
                    'options' => [
                        'limitation' => [
                            'label' => 'Korlátozás',
                        ],
                        'exclusion' => [
                            'label' => 'Kizárás',
                        ],
                    ],
                ],
                'purchasable' => [
                    'label' => 'Vásárolható tétel',
                    'types' => [
                        'product' => [
                            'label' => 'Termék',
                        ],
                    ],
                ],
            ],
        ],
        'conditions' => [
            'title' => 'Termékfeltételek',
            'description' => 'Válassza ki a kedvezmény érvényesítéséhez szükséges termékeket.',
            'actions' => [
                'attach' => [
                    'label' => 'Termék hozzáadása',
                    'modal' => [
                        'heading' => 'Kedvezményes tétel létrehozása',
                    ],
                ],
                'delete' => [
                    'modal' => [
                        'heading' => 'Kedvezményes tétel törlése',
                        'bulk' => [
                            'heading' => 'Kiválasztott kedvezményes tételek törlése',
                        ],
                    ]
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Név',
                ],
                'type' => [
                    'label' => 'Típus',
                    'limitation' => [
                        'label' => 'Korlátozás',
                    ],
                    'exclusion' => [
                        'label' => 'Kizárás',
                    ],
                ],
            ],
            'form' => [
                'type' => [
                    'options' => [
                        'limitation' => [
                            'label' => 'Korlátozás',
                        ],
                        'exclusion' => [
                            'label' => 'Kizárás',
                        ],
                    ],
                ],
                'purchasable' => [
                    'label' => 'Vásárolható tétel',
                    'types' => [
                        'product' => [
                            'label' => 'Termék',
                        ],
                    ],
                ],
            ],
        ],
        'productvariants' => [
            'title' => 'Termékváltozatok',
            'description' => 'Válassza ki, mely termékváltozatokra vonatkozzon ez a kedvezmény.',
            'actions' => [
                'attach' => [
                    'label' => 'Termékváltozat hozzáadása',
                    'modal' => [
                        'heading' => 'Kedvezményes termékváltozat létrehozása',
                    ],
                ],
                'delete' => [
                     'modal' => [
                         'heading' => 'Kedvezményes tétel törlése',
                         'bulk' => [
                             'heading' => 'Kiválasztott kedvezményes tételek törlése',
                         ],
                     ]
                ],
            ],
            'table' => [
                'name' => [
                    'label' => 'Név',
                ],
                'sku' => [
                    'label' => 'SKU (Egyedi azonosító)',
                ],
                'values' => [
                    'label' => 'Opció(k)',
                ],
            ],
            'form' => [
                'type' => [
                    'options' => [
                        'limitation' => [
                            'label' => 'Korlátozás',
                        ],
                        'exclusion' => [
                            'label' => 'Kizárás',
                        ],
                    ],
                ],
                'purchasable' => [
                    'label' => 'Vásárolható tétel',
                    'types' => [
                        'product_variant' => [
                            'label' => 'Termékváltozat',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
