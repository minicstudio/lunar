<?php

return [
    'customer_groups' => [
        'actions' => [
            'attach' => [
                'label' => 'Vevőcsoport csatolása',
            ],
            'edit' => [
                'modal' => [
                    'heading' => 'Vevőcsoport szerkesztése',
                ],
            ],
        ],
        'form' => [
            'name' => [
                'label' => 'Név',
            ],
            'enabled' => [
                'label' => 'Engedélyezve',
            ],
            'starts_at' => [
                'label' => 'Kezdő dátum',
            ],
            'ends_at' => [
                'label' => 'Lejárati dátum',
            ],
            'visible' => [
                'label' => 'Látható',
            ],
            'purchasable' => [
                'label' => 'Vásárolható',
            ],
        ],
        'table' => [
            'description' => 'Vevőcsoportokat társíthat ehhez a :type típushoz az elérhetőség meghatározásához.',
            'name' => [
                'label' => 'Név',
            ],
            'enabled' => [
                'label' => 'Engedélyezve',
            ],
            'starts_at' => [
                'label' => 'Kezdő dátum',
            ],
            'ends_at' => [
                'label' => 'Lejárati dátum',
            ],
            'visible' => [
                'label' => 'Látható',
            ],
            'purchasable' => [
                'label' => 'Vásárolható',
            ],
        ],
    ],
    'channels' => [
        'actions' => [
            'attach' => [
                'label' => 'Csatorna ütemezése',
            ],
            'edit' => [
                'modal' => [
                    'heading' => 'Csatorna szerkesztése',
                ],
            ],
        ],
        'form' => [
            'enabled' => [
                'label' => 'Engedélyezve',
                'helper_text_false' => 'Ez a csatorna nem lesz engedélyezve még akkor sem, ha kezdő dátum meg van adva.',
            ],
            'starts_at' => [
                'label' => 'Kezdő dátum',
                'helper_text' => 'Hagyja üresen, hogy bármely dátumtól elérhető legyen.',
            ],
            'ends_at' => [
                'label' => 'Lejárati dátum',
                'helper_text' => 'Hagyja üresen, hogy korlátlan ideig elérhető legyen.',
            ],
        ],
        'table' => [
            'description' => 'Határozza meg, mely csatornák engedélyezettek és ütemezze elérhetőségüket.',
            'name' => [
                'label' => 'Név',
            ],
            'enabled' => [
                'label' => 'Engedélyezve',
            ],
            'starts_at' => [
                'label' => 'Kezdő dátum',
            ],
            'ends_at' => [
                'label' => 'Lejárati dátum',
            ],
        ],
    ],
    'medias' => [
        'title' => 'Média',
        'title_plural' => 'Médiák',
        'actions' => [
            'attach' => [
                'label' => 'Média csatolása',
            ],
            'create' => [
                'label' => 'Média létrehozása',
            ],
            'detach' => [
                'label' => 'Leválasztás',
            ],
            'view' => [
                'label' => 'Megtekintés',
            ],
        ],
        'form' => [
            'name' => [
                'label' => 'Név',
            ],
            'media' => [
                'label' => 'Kép',
            ],
            'primary' => [
                'label' => 'Elsődleges',
            ],
        ],
        'table' => [
            'image' => [
                'label' => 'Kép',
            ],
            'file' => [
                'label' => 'Fájl',
            ],
            'name' => [
                'label' => 'Név',
            ],
            'primary' => [
                'label' => 'Elsődleges',
            ],
        ],
        'all_media_attached' => 'Nincsenek termékképek, amelyeket csatolni lehetne',
        'variant_description' => 'Termékképek csatolása ehhez a változathoz',
    ],
    'urls' => [
        'title' => 'URL',
        'title_plural' => 'URL-ek',
        'actions' => [
            'create' => [
                'label' => 'URL létrehozása',
                'modal' => [
                    'heading' => 'URL létrehozása',
                ],
            ],
        ],
        'filters' => [
            'language_id' => [
                'label' => 'Nyelv',
            ],
        ],
        'form' => [
            'slug' => [
                'label' => 'Slug',
            ],
            'default' => [
                'label' => 'Alapértelmezett',
            ],
            'language' => [
                'label' => 'Nyelv',
            ],
        ],
        'table' => [
            'slug' => [
                'label' => 'Slug',
            ],
            'default' => [
                'label' => 'Alapértelmezett',
            ],
            'language' => [
                'label' => 'Nyelv',
            ],
        ],
    ],
    'customer_group_pricing' => [
        'title' => 'Vevőcsoport árképzés',
        'title_plural' => 'Vevőcsoport árképzés',
        'table' => [
            'heading' => 'Vevőcsoport árképzés',
            'description' => 'Ár társítása vevőcsoportokhoz a termék árának meghatározásához.',
            'empty_state' => [
                'label' => 'Nincs vevőcsoport árképzés.',
                'description' => 'Hozzon létre vevőcsoport árát a kezdéshez.',
            ],
            'actions' => [
                'create' => [
                    'label' => 'Vevőcsoport ár hozzáadása',
                    'modal' => [
                        'heading' => 'Vevőcsoport ár létrehozása',
                    ],
                ],
            ],
        ],
    ],
    'pricing' => [
        'title' => 'Árképzés',
        'title_plural' => 'Árképzések',
        'tab_name' => 'Árengedmények',
        'table' => [
            'heading' => 'Árengedmények',
            'description' => 'Csökkentse az árat, amikor egy vevő nagyobb mennyiséget vásárol.',
            'empty_state' => [
                'label' => 'Nincsenek árengedmények.',
            ],
            'actions' => [
                'create' => [
                    'label' => 'Árengedmény hozzáadása',
                ],
            ],
            'price' => [
                'label' => 'Ár',
            ],
            'customer_group' => [
                'label' => 'Vevőcsoport',
                'placeholder' => 'Minden vevőcsoport',
            ],
            'min_quantity' => [
                'label' => 'Minimális mennyiség',
            ],
            'currency' => [
                'label' => 'Pénznem',
            ],
        ],
        'form' => [
            'price' => [
                'label' => 'Ár',
                'helper_text' => 'Vásárlási ár, kedvezmények előtt.',
            ],
            'customer_group_id' => [
                'label' => 'Vevőcsoport',
                'placeholder' => 'Minden vevőcsoport',
                'helper_text' => 'Válassza ki, mely vevőcsoporthoz alkalmazza ezt az árat.',
            ],
            'min_quantity' => [
                'label' => 'Minimális mennyiség',
                'helper_text' => 'Válassza ki a minimális mennyiséget, amire ez az ár érvényes.',
                'validation' => [
                    'unique' => 'A vevőcsoport és a minimális mennyiség egyedinek kell lennie.',
                ],
            ],
            'currency_id' => [
                'label' => 'Pénznem',
                'helper_text' => 'Válassza ki a pénznemet ehhez az árhoz.',
            ],
            'compare_price' => [
                'label' => 'Összehasonlító ár',
                'helper_text' => 'Az eredeti ár vagy ajánlott kiskereskedelmi ár az összehasonlításhoz.',
            ],
            'basePrices' => [
                'title' => 'Árak',
                'form' => [
                    'price' => [
                        'label' => 'Ár',
                        'helper_text' => 'Vásárlási ár, kedvezmények előtt.',
                    ],
                    'compare_price' => [
                        'label' => 'Összehasonlító ár',
                        'helper_text' => 'Az eredeti ár vagy ajánlott kiskereskedelmi ár az összehasonlításhoz.',
                    ],
                ],
                'tooltip' => 'Automatikusan generált az árfolyamok alapján.',
            ],
        ],
    ],
    'tax_rate_amounts' => [
        'table' => [
            'description' => '',
            'percentage' => [
                'label' => 'Százalék',
            ],
            'tax_class' => [
                'label' => 'Adóosztály',
            ],
        ],
    ],
];
