<?php

return [
    'label' => 'Menu Item',
    'plural_label' => 'Menu Items',

    'edit' => [
        'label' => 'Edit Menu Item',
    ],

    'sections' => [
        'content' => 'Link',
        'visibility' => 'Visibility',
    ],

    'form' => [
        'link_type' => [
            'label' => 'Link Type',
            'options' => [
                'collection' => 'Collection',
                'cms_page' => 'CMS Page',
                'contact' => 'Contact',
                'custom_url' => 'Custom URL',
            ],
        ],
        'collection_id' => [
            'label' => 'Collection',
        ],
        'cms_page' => [
            'label' => 'CMS Page',
            'options' => [
                'about_us' => 'About us',
                'faq' => 'FAQ',
                'privacy_policy' => 'Privacy Policy',
                'terms_and_conditions' => 'Terms and Conditions',
                'delivery_and_return' => 'Delivery and Return',
                'cookie_policy' => 'Cookie Policy',
            ],
        ],
        'label' => [
            'label' => 'Label',
            'helper' => 'Optional. Leave empty to use the collection name, CMS page title, “Contact”, or the custom URL.',
        ],
        'custom_url' => [
            'label' => 'Custom URL',
            'helper' => 'Enter a full external URL (https://...) or a relative path (/my-page).',
        ],
        'is_active' => [
            'label' => 'Active',
        ],
    ],

    'table' => [
        'label' => [
            'label' => 'Label',
        ],
        'link_type' => [
            'label' => 'Type',
        ],
        'target' => [
            'label' => 'Target',
        ],
        'is_active' => [
            'label' => 'Active',
        ],
    ],
];
