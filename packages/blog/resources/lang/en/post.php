<?php

return [
    'label' => 'Post',
    'plural_label' => 'Posts',

    'edit' => [
        'label' => 'Basic Information',
    ],

    'form' => [
        'title' => [
            'label' => 'Title',
        ],
        'handle' => [
            'label' => 'Handle',
        ],
        'status' => [
            'label' => 'Status',
            'options' => [
                'published' => [
                    'label' => 'Published',
                    'description' => 'This post will be available across all enabled channels',
                ],
                'draft' => [
                    'label' => 'Draft',
                    'description' => 'This post will be hidden across all channels',
                ],
            ],
        ],
        'categories' => [
            'title' => [
                'label' => 'Categories',
            ],
        ],
    ],

    'section' => [
        'categories' => [
            'title' => 'Blog Categories',
            'description' => 'Manage the categories for this blog post. Select an existing category or create a new one if needed.',
            'action' => [
                'label' => 'New Category',
                'modal' => [
                    'heading' => 'Create Category',
                    'submit' => 'Create',
                ],
                'notification' => [
                    'title' => 'Category Created',
                    'body' => 'The category was created successfully.',
                ],
            ],
        ],
    ],

    'table' => [
        'title' => [
            'label' => 'Title',
        ],
        'status' => [
            'label' => 'Status',
            'states' => [
                'draft' => 'Draft',
                'published' => 'Published',
            ],
        ],
        'author' => [
            'label' => 'Author',
        ],
    ],

    'actions' => [
        'edit_status' => [
            'label' => 'Update Status',
            'heading' => 'Update Status',
        ],
        'preview' => [
            'label' => 'Preview',
        ],
    ],

    'pages' => [
        'availability' => [
            'label' => 'Availability',
        ],
    ],

    'filters' => [
        'status' => [
            'label' => 'Status',
            'placeholder' => 'Select status',
            'options' => [
                'draft' => 'Draft',
                'published' => 'Published',
            ],
        ],
        'author' => [
            'label' => 'Author',
            'placeholder' => 'Select an author',
        ],
    ],
];
