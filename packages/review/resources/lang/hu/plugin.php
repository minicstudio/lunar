<?php

return [
    'label' => 'Értékelés',
    'plural_label' => 'Értékelések',

    'table' => [
        'order_reference' => [
            'label' => 'Rendelési hivatkozás',
        ],
        'rating' => [
            'label' => 'Értékelés',
        ],
        'approved_at' => [
            'label' => 'Állapot',
            'states' => [
                'approved' => 'Jóváhagyva',
                'not_approved' => 'Nincs jóváhagyva',
            ],
        ],
        'model' => [
            'type' => [
                'label' => 'Modell típus',
            ],
            'name' => [
                'label' => 'Modell név',
            ],
        ],
        'product' => [
            'name' => [
                'label' => 'Termék név',
            ],
        ],
        'product_variant' => [
            'name' => [
                'label' => 'Termékvariáns név',
            ],
        ],
        'channel' => [
            'name' => [
                'label' => 'Csatorna név',
            ],
        ],
    ],

    'actions' => [
        'manage_order' => [
            'label' => 'Menjen a rendeléshez',
        ],
    ],

    'form' => [
        'model' => [
            'default' => 'Modell',
            'product' => 'Termék név',
            'product_variant' => 'Termékvariáns név',
            'channel' => 'Csatorna név',
        ],
        'upload_images_section' => 'Képek',
        'upload_images' => 'Képek feltöltése',
        'with_options' => 'A következő opciókkal: ',
        'approved_at' => 'Jóváhagyva',
        'approved' => 'Jóváhagyva',
        'not_approved' => 'Nincs jóváhagyva',
    ],

    'filters' => [
        'status' => [
            'label' => 'Állapot',
            'options' => [
                'approved' => 'Jóváhagyva',
                'not_approved' => 'Nincs jóváhagyva',
            ],
            'placeholder' => 'Válassz állapotot',
        ],
        'rating' => [
            'label' => 'Értékelés',
            'indicator' => 'Értékelés: :rating',
        ],
    ],

    'relationManagers' => [
        'product' => [
            'title' => 'Termék',
            'heading' => 'Termék vélemények',
        ],
        'product_variant' => [
            'title' => 'Termékvariáns',
            'heading' => 'Termékvariáns vélemények',
        ],
        'channel' => [
            'title' => 'Csatorna',
            'heading' => 'Csatorna vélemények',
        ],
    ],
];
