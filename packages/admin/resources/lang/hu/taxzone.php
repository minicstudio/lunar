<?php

return [

    'label' => 'Adózóna',

    'plural_label' => 'Adózónák',

    'table' => [
        'name' => [
            'label' => 'Név',
        ],
        'zone_type' => [
            'label' => 'Zóna típusa',
        ],
        'active' => [
            'label' => 'Aktív',
        ],
        'default' => [
            'label' => 'Alapértelmezett',
        ],
    ],

    'form' => [
        'name' => [
            'label' => 'Név',
        ],
        'zone_type' => [
            'label' => 'Zóna típusa',
            'options' => [
                'country' => 'Országokra korlátozva',
                'states' => 'Államokra korlátozva',
                'postcodes' => 'Irányítószámokra korlátozva',
            ],
        ],
        'price_display' => [
            'label' => 'Ár megjelenítése',
            'options' => [
                'include_tax' => 'Adóval együtt',
                'exclude_tax' => 'Adó nélkül',
            ],
        ],
        'active' => [
            'label' => 'Aktív',
        ],
        'default' => [
            'label' => 'Alapértelmezett',
        ],

        'zone_countries' => [
            'label' => 'Országok',
        ],

        'zone_country' => [
            'label' => 'Ország',
        ],

        'zone_states' => [
            'label' => 'Államok',
        ],

        'zone_postcodes' => [
            'label' => 'Irányítószámok',
            'helper' => 'Minden irányítószámot új sorba írj. Helyettesítő karakterek is használhatók, pl. NW*',
        ],

    ],

];
