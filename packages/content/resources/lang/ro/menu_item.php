<?php

return [
    'label' => 'Element de meniu',
    'plural_label' => 'Elemente de meniu',

    'edit' => [
        'label' => 'Editare element de meniu',
    ],

    'sections' => [
        'content' => 'Link',
        'visibility' => 'Vizibilitate',
    ],

    'form' => [
        'link_type' => [
            'label' => 'Tip link',
            'options' => [
                'collection' => 'Colecție',
                'cms_page' => 'Pagină CMS',
                'contact' => 'Contact',
            ],
        ],
        'collection_id' => [
            'label' => 'Colecție',
        ],
        'cms_page' => [
            'label' => 'Pagină CMS',
            'options' => [
                'about_us' => 'Despre noi',
                'faq' => 'Întrebări frecvente',
                'privacy_policy' => 'Politica de confidențialitate',
                'terms_and_conditions' => 'Termeni și condiții',
                'delivery_and_return' => 'Livrare și retur',
                'cookie_policy' => 'Politica de cookie-uri',
            ],
        ],
        'label' => [
            'label' => 'Etichetă',
            'helper' => 'Opțional. Lasă gol pentru a folosi numele colecției, titlul paginii CMS sau „Contact”.',
        ],
        'is_active' => [
            'label' => 'Activ',
        ],
    ],

    'table' => [
        'label' => [
            'label' => 'Etichetă',
        ],
        'link_type' => [
            'label' => 'Tip',
        ],
        'target' => [
            'label' => 'Țintă',
        ],
        'is_active' => [
            'label' => 'Activ',
        ],
    ],
];
