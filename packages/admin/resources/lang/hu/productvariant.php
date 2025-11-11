<?php

return [
    'label' => 'Termékváltozat',
    'plural_label' => 'Termékváltozatok',
    'pages' => [
        'edit' => [
            'title' => 'Alapvető Információk',
        ],
        'media' => [
            'title' => 'Média',
            'form' => [
                'no_selection' => [
                    'label' => 'Jelenleg nincs kiválasztott kép ehhez a változathoz.',
                ],
                'no_media_available' => [
                    'label' => 'Jelenleg nincs elérhető média ezen a terméken.',
                ],
                'images' => [
                    'label' => 'Elsődleges kép',
                    'helper_text' => 'Válassza ki a termékképet, amely ezt a változatot képviseli.',
                ],
            ],
        ],
        'identifiers' => [
            'title' => 'Azonosítók',
        ],
        'inventory' => [
            'title' => 'Készlet',
        ],
        'shipping' => [
            'title' => 'Szállítás',
        ],
    ],
    'form' => [
        'sku' => [
            'label' => 'SKU (Egyedi azonosító)',
        ],
        'gtin' => [
            'label' => 'Globális kereskedelmi cikkszám (GTIN)',
        ],
        'mpn' => [
            'label' => 'Gyártói cikkszám (MPN)',
        ],
        'ean' => [
            'label' => 'Globális termékkód (UPC/EAN)',
        ],
        'stock' => [
            'label' => 'Készleten',
        ],
        'backorder' => [
            'label' => 'Előrendelhető',
        ],
        'purchasable' => [
            'label' => 'Vásárolhatóság',
            'options' => [
                'always' => 'Mindig',
                'in_stock' => 'Készleten',
                'in_stock_or_on_backorder' => 'Készleten vagy előrendelhető',
            ],
        ],
        'unit_quantity' => [
            'label' => 'Egység mennyisége',
            'helper_text' => 'Hány egyedi tétel alkot 1 egységet.',
        ],
        'min_quantity' => [
            'label' => 'Minimális mennyiség',
            'helper_text' => 'A termékváltozatból egyszerre megvásárolható minimum mennyiség.',
        ],
        'quantity_increment' => [
            'label' => 'Mennyiség növelés',
            'helper_text' => 'A termékváltozatot ennek a mennyiségnek a többszöröseiben kell megvásárolni.',
        ],
        'tax_class_id' => [
            'label' => 'Adóosztály',
        ],
        'shippable' => [
            'label' => 'Szállítható',
        ],
        'length_value' => [
            'label' => 'Hosszúság',
        ],
        'length_unit' => [
            'label' => 'Hosszúság egység',
        ],
        'width_value' => [
            'label' => 'Szélesség',
        ],
        'width_unit' => [
            'label' => 'Szélesség egység',
        ],
        'height_value' => [
            'label' => 'Magasság',
        ],
        'height_unit' => [
            'label' => 'Magasság egység',
        ],
        'weight_value' => [
            'label' => 'Súly',
        ],
        'weight_unit' => [
            'label' => 'Súly egység',
        ],
    ],
];
