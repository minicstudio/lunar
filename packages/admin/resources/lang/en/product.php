<?php

return [

    'label' => 'Product',

    'plural_label' => 'Products',

    'tabs' => [
        'all' => 'All',
    ],

    'status' => [
        'unpublished' => [
            'content' => 'Currently in draft status, this product is hidden across all channels and customer groups.',
        ],
        'availability' => [
            'customer_groups' => 'This product is currently unavailable for all customer groups.',
            'channels' => 'This product is currently unavailable for all channels.',
        ],
    ],

    'table' => [
        'status' => [
            'label' => 'Status',
            'states' => [
                'deleted' => 'Deleted',
                'draft' => 'Draft',
                'published' => 'Published',
            ],
        ],
        'name' => [
            'label' => 'Name',
        ],
        'brand' => [
            'label' => 'Brand',
        ],
        'sku' => [
            'label' => 'SKU',
        ],
        'stock' => [
            'label' => 'Stock',
        ],
        'producttype' => [
            'label' => 'Product Type',
        ],
    ],

    'actions' => [
        'edit_status' => [
            'label' => 'Update Status',
            'heading' => 'Update Status',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Name',
        ],
        'brand' => [
            'label' => 'Brand',
        ],
        'sku' => [
            'label' => 'SKU',
        ],
        'base_price' => [
            'label' => 'Base price',
        ],
        'producttype' => [
            'label' => 'Product Type',
        ],
        'status' => [
            'label' => 'Status',
            'options' => [
                'published' => [
                    'label' => 'Published',
                    'description' => 'This product will be available across all enabled customer groups and channels',
                ],
                'draft' => [
                    'label' => 'Draft',
                    'description' => 'This product will be hidden across all channels and customer groups',
                ],
            ],
        ],
        'tags' => [
            'label' => 'Tags',
            'helper_text' => 'Separate tags by pressing Enter, Tab or comma (,)',
        ],
        'collections' => [
            'label' => 'Collections',
            'select_collection' => 'Select a collection',
        ],
    ],

    'pages' => [
        'availability' => [
            'label' => 'Availability',
        ],
        'edit' => [
            'title' => 'Basic Information',
        ],
        'identifiers' => [
            'label' => 'Product Identifiers',
        ],
        'inventory' => [
            'label' => 'Inventory',
        ],
        'pricing' => [
            'form' => [
                'tax_class_id' => [
                    'label' => 'Tax Class',
                ],
                'tax_ref' => [
                    'label' => 'Tax Reference',
                    'helper_text' => 'Optional, for integration with 3rd party systems.',
                ],
            ],
        ],
        'shipping' => [
            'label' => 'Shipping',
        ],
        'variants' => [
            'label' => 'Variants',
        ],
        'collections' => [
            'label' => 'Collections',
            'actions' => [
                'attach' => [
                    'heading' => 'Attach Collection',
                    'form' => [
                        'collection' => [
                            'placeholder' => 'Select a collection',
                        ],
                    ],
                ],
                'detach' => [
                    'heading' => 'Detach Collection',
                    'bulk' => [
                        'heading' => 'Detach selected collections',
                    ],
                ],
            ],
        ],
        'associations' => [
            'label' => 'Product Associations',
            'actions' => [
                'create' => [
                    'label' => 'New product association',
                    'heading' => 'Create product association',
                ],
                'delete' => [
                    'heading' => 'Delete product association',
                    'bulk' => [
                        'heading' => 'Delete selected product associations',
                    ],
                ],
            ],
            'form' => [
                'target' => [
                    'label' => 'Product',
                ],
                'type' => [
                    'label' => 'Type',
                    'options' => [
                        'alternate' => 'Alternate',
                        'cross-sell' => 'Cross-sell',
                        'up-sell' => 'Up-sell',
                    ],
                ],
            ],
        ],
    ],

];
