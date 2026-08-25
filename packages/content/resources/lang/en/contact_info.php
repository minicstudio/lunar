<?php

return [
    'label' => 'Contact Details',
    'plural_label' => 'Contact Details',

    'edit' => [
        'label' => 'Edit Contact Details',
    ],

    'sections' => [
        'content' => 'Contact',
        'address' => 'Address',
        'visibility' => 'Visibility',
    ],

    'form' => [
        'intro' => [
            'label' => 'Intro text',
            'helper' => 'Optional paragraph shown above the contact details on the contact page.',
        ],
        'phone' => [
            'label' => 'Phone',
        ],
        'email' => [
            'label' => 'Email',
        ],
        'street' => [
            'label' => 'Street',
        ],
        'city' => [
            'label' => 'City',
        ],
        'postal_code' => [
            'label' => 'Postal code',
        ],
        'country' => [
            'label' => 'Country',
        ],
        'country_code' => [
            'label' => 'Country code',
            'helper' => 'ISO 3166-1 alpha-2 (e.g. HU, RO). Used in schema markup.',
        ],
        'is_active' => [
            'label' => 'Active',
            'helper' => 'When inactive, the storefront falls back to config / hardcoded contact details.',
        ],
    ],

    'table' => [
        'email' => [
            'label' => 'Email',
        ],
        'phone' => [
            'label' => 'Phone',
        ],
        'is_active' => [
            'label' => 'Active',
        ],
    ],
];
