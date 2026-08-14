<?php

return [
    'label' => 'Menüpont',
    'plural_label' => 'Menüpontok',

    'edit' => [
        'label' => 'Menüpont szerkesztése',
    ],

    'sections' => [
        'content' => 'Link',
        'visibility' => 'Láthatóság',
    ],

    'form' => [
        'link_type' => [
            'label' => 'Link típusa',
            'options' => [
                'collection' => 'Kollekció',
                'cms_page' => 'CMS oldal',
                'contact' => 'Kapcsolat',
            ],
        ],
        'collection_id' => [
            'label' => 'Kollekció',
        ],
        'cms_page' => [
            'label' => 'CMS oldal',
            'options' => [
                'about_us' => 'Rólunk',
                'faq' => 'GYIK',
                'privacy_policy' => 'Adatvédelmi irányelvek',
                'terms_and_conditions' => 'Általános szerződési feltételek',
                'delivery_and_return' => 'Szállítás és visszaküldés',
                'cookie_policy' => 'Cookie szabályzat',
            ],
        ],
        'label' => [
            'label' => 'Felirat',
            'helper' => 'Opcionális. Ha üresen hagyod, a kollekció neve, a CMS oldal címe vagy a „Kapcsolat” jelenik meg.',
        ],
        'is_active' => [
            'label' => 'Aktív',
        ],
    ],

    'table' => [
        'label' => [
            'label' => 'Felirat',
        ],
        'link_type' => [
            'label' => 'Típus',
        ],
        'target' => [
            'label' => 'Cél',
        ],
        'is_active' => [
            'label' => 'Aktív',
        ],
    ],
];
