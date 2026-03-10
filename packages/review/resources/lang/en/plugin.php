<?php

return [
    'label' => 'Review',
    'plural_label' => 'Reviews',

    'table' => [
        'order_reference' => [
            'label' => 'Order Reference',
        ],
        'rating' => [
            'label' => 'Rating',
        ],
        'approved_at' => [
            'label' => 'Status',
            'states' => [
                'approved' => 'Approved',
                'not_approved' => 'Not Approved',
            ],
        ],
        'model' => [
            'type' => [
                'label' => 'Model Type',
            ],
            'name' => [
                'label' => 'Model Name',
            ],
        ],
        'product' => [
            'name' => [
                'label' => 'Product Name',
            ],
        ],

        'product_variant' => [
            'name' => [
                'label' => 'Product Variant Name',
            ],
        ],
        'channel' => [
            'name' => [
                'label' => 'Channel Name',
            ],
        ],
    ],

    'actions' => [
        'manage_order' => [
            'label' => 'Go to order',
        ],
    ],

    'form' => [
        'model' => [
            'default' => 'Model',
            'product' => 'Product Name',
            'product_variant' => 'Product Variant Name',
            'channel' => 'Channel Name',
        ],
        'upload_images_section' => 'Images',
        'upload_images' => 'Upload Images',
        'with_options' => 'With options: ',
        'approved_at' => 'Approved At',
        'approved' => 'Approved',
        'not_approved' => 'Not Approved',
    ],

    'filters' => [
        'status' => [
            'label' => 'Status',
            'options' => [
                'approved' => 'Approved',
                'not_approved' => 'Not Approved',
            ],
            'placeholder' => 'Select Status',
        ],
        'rating' => [
            'label' => 'Rating',
            'indicator' => 'Rating: :rating',
        ],
    ],

    'relationManagers' => [
        'product' => [
            'title' => 'Product',
            'heading' => 'Product Reviews',
        ],
        'product_variant' => [
            'title' => 'Product Variant',
            'heading' => 'Product Variant Reviews',
        ],
        'channel' => [
            'title' => 'Channel',
            'heading' => 'Channel Reviews',
        ],
    ],
];
