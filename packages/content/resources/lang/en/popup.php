<?php

return [
    'label' => 'Popup',
    'plural_label' => 'Popups',

    'edit' => [
        'label' => 'Edit Popup',
    ],

    'sections' => [
        'content' => 'Popup Content',
        'timing' => 'Timing & Visibility',
    ],

    'form' => [
        'title' => [
            'label' => 'Title',
        ],
        'body' => [
            'label' => 'Body',
        ],
        'discount_code' => [
            'label' => 'Discount Code',
            'helper' => 'Optional code shown in the popup for visitors to copy.',
        ],
        'cta_label' => [
            'label' => 'Button Label',
        ],
        'cta_url' => [
            'label' => 'Button URL',
        ],
        'display_on' => [
            'label' => 'Show On Pages',
            'helper' => 'The popup only appears on the selected pages.',
            'options' => [
                'home' => 'Homepage',
                'collection' => 'Collection / list pages',
                'product' => 'Product pages',
                'search' => 'Search results',
                'cart' => 'Cart',
                'checkout' => 'Checkout',
                'blog' => 'Blog',
                'other' => 'Other pages',
            ],
        ],
        'delay_seconds' => [
            'label' => 'Delay (seconds)',
            'helper' => 'How long to wait after page load before showing the popup.',
        ],
        'width_percentage' => [
            'label' => 'Popup Width',
            'helper' => 'Popup width as a percentage of the viewport (30-100%).',
        ],
        'show_once' => [
            'label' => 'Show Once',
            'helper' => 'When enabled, dismissed popups stay hidden for this visitor.',
        ],
        'is_active' => [
            'label' => 'Active',
        ],
        'sort_order' => [
            'label' => 'Sort Order',
        ],
        'starts_at' => [
            'label' => 'Starts At',
            'helper' => 'Optional. Popup only appears from this date/time.',
        ],
        'ends_at' => [
            'label' => 'Ends At',
            'helper' => 'Optional. Popup stops appearing after this date/time.',
        ],
    ],

    'table' => [
        'title' => [
            'label' => 'Title',
        ],
        'delay_seconds' => [
            'label' => 'Delay',
        ],
        'display_on' => [
            'label' => 'Pages',
            'none' => 'None',
        ],
        'is_active' => [
            'label' => 'Active',
        ],
        'show_once' => [
            'label' => 'Show Once',
        ],
        'starts_at' => [
            'label' => 'Starts At',
        ],
        'ends_at' => [
            'label' => 'Ends At',
        ],
    ],
];
