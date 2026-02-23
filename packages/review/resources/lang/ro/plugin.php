<?php

return [
    'label' => 'Recenzie',
    'plural_label' => 'Recenzii',

    'table' => [
        'order_reference' => [
            'label' => 'Nr. de referință comandă',
        ],
        'rating' => [
            'label' => 'Evaluare',
        ],
        'approved_at' => [
            'label' => 'Status',
            'states' => [
                'approved' => 'Aprobat',
                'not_approved' => 'Neaprobat',
            ],
        ],
        'model' => [
            'type' => [
                'label' => 'Tipul Modelului',
            ],
            'name' => [
                'label' => 'Numele Modelului',
            ],
        ],
        'product' => [
            'name' => [
                'label' => 'Numele Produsului',
            ],
        ],
        'product_variant' => [
            'name' => [
                'label' => 'Nume variantă produs',
            ],
        ],
        'channel' => [
            'name' => [
                'label' => 'Numele Canalului',
            ],
        ],
    ],

    'actions' => [
        'manage_order' => [
            'label' => 'Procedați la comandă',
        ],
    ],

    'form' => [
        'model' => [
            'default' => 'Modell',
            'product' => 'Numele Produsului',
            'product_variant' => 'Nume variantă produs',
            'channel' => 'Numele Canalului',
        ],
        'upload_images_section' => 'Imagini',
        'upload_images' => 'Încarcă imagini',
        'with_options' => 'Cu opțiuni: ',
        'approved_at' => 'Aprobat la',
        'approved' => 'Aprobat',
        'not_approved' => 'Neaprobat',
    ],

    'filters' => [
        'status' => [
            'label' => 'Status',
            'options' => [
                'approved' => 'Aprobat',
                'not_approved' => 'Neaprobat',
            ],
            'placeholder' => 'Selectează status',
        ],
        'rating' => [
            'label' => 'Evaluare',
            'indicator' => 'Evaluare: :rating',
        ],
    ],

    'relationManagers' => [
        'product' => [
            'title' => 'Produse',
            'heading' => 'Recenzii produse',
        ],
        'product_variant' => [
            'title' => 'Variantă Produs',
            'heading' => 'Recenzii Variante Produs',
        ],
        'channel' => [
            'title' => 'Canal',
            'heading' => 'Recenzii Canal',
        ],
    ],
];
