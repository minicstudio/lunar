<?php

return [

    'label' => 'Termék',

    'plural_label' => 'Termékek',

    'status' => [
        'unpublished' => [
            'content' => 'Jelenleg piszkozat státuszban van, ez a termék minden csatornán és vásárlói csoportban rejtve van.',
        ],
        'availability' => [
            'customer_groups' => 'Ez a termék jelenleg nem elérhető egyik vásárlói csoport számára sem.',
            'channels' => 'Ez a termék jelenleg nem elérhető egyik csatornán sem.',
        ],
    ],

    'table' => [
        'status' => [
            'label' => 'Állapot',
            'states' => [
                'deleted' => 'Törölve',
                'draft' => 'Piszkozat',
                'published' => 'Publikálva',
            ],
        ],
        'name' => [
            'label' => 'Név',
        ],
        'brand' => [
            'label' => 'Márka',
        ],
        'sku' => [
            'label' => 'SKU',
        ],
        'stock' => [
            'label' => 'Készlet',
        ],
        'producttype' => [
            'label' => 'Terméktípus',
        ],
    ],

    'actions' => [
        'edit_status' => [
            'label' => 'Állapot frissítése',
            'heading' => 'Állapot frissítése',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Név',
        ],
        'brand' => [
            'label' => 'Márka',
        ],
        'sku' => [
            'label' => 'SKU',
        ],
        'producttype' => [
            'label' => 'Terméktípus',
        ],
        'status' => [
            'label' => 'Állapot',
            'options' => [
                'published' => [
                    'label' => 'Publikálva',
                    'description' => 'Ez a termék minden engedélyezett vásárlói csoport és csatorna számára elérhető lesz',
                ],
                'draft' => [
                    'label' => 'Piszkozat',
                    'description' => 'Ez a termék minden csatornán és vásárlói csoportban rejtve lesz',
                ],
            ],
        ],
        'tags' => [
            'label' => 'Címkék',
            'helper_text' => 'A címkék elválasztásához használj Entert, Tabot vagy vesszőt (,)',
        ],
        'collections' => [
            'label' => 'Gyűjtemények',
        ],
    ],

    'pages' => [
        'availability' => [
            'label' => 'Elérhetőség',
        ],
        'edit' => [
            'title' => 'Alapvető információk',
        ],
        'identifiers' => [
            'label' => 'Termékazonosítók',
        ],
        'inventory' => [
            'label' => 'Készlet',
        ],
        'pricing' => [
            'form' => [
                'tax_class_id' => [
                    'label' => 'Adóosztály',
                ],
                'tax_ref' => [
                    'label' => 'Adó referencia',
                    'helper_text' => 'Opcionális, harmadik fél rendszerekkel való integrációhoz.',
                ],
            ],
        ],
        'shipping' => [
            'label' => 'Szállítás',
        ],
        'variants' => [
            'label' => 'Változatok',
        ],
        'collections' => [
            'label' => 'Gyűjtemények',
        ],
        'associations' => [
            'label' => 'Termék asszociációk',
        ],
    ],

];
