<?php

return [
    'page' => [
        'title' => 'Surse de trafic',
        'navigation_label' => 'Surse de trafic',
    ],

    'filters' => [
        'from' => 'De la',
        'to' => 'Până la',
    ],

    'stats' => [
        'visits' => 'Vizite',
        'carts' => 'Coșuri',
        'carts_description' => 'Coșuri create cu o sursă de trafic',
        'orders' => 'Comenzi',
        'orders_description' => ':count cu o sursă de trafic',
        'order_value' => 'Valoare comenzi',
        'order_rate' => 'Rată de comandă',
        'order_rate_description' => 'Comenzi cu sursă de trafic per vizită',
    ],

    'widgets' => [
        'visits_by_source' => [
            'heading' => 'Vizite pe surse',
        ],
        'visits_over_time' => [
            'heading' => [
                'day' => 'Vizite pe zi',
                'week' => 'Vizite pe săptămână',
                'month' => 'Vizite pe lună',
            ],
            'series' => 'Vizite',
        ],
        'funnel' => [
            'heading' => 'Coșuri și comenzi pe surse',
        ],
        'landing_pages' => [
            'heading' => 'Pagini de destinație',
        ],
    ],

    'columns' => [
        'source' => 'Sursă',
        'medium' => 'Mediu',
        'visits' => 'Vizite',
        'carts' => 'Coșuri',
        'orders' => 'Comenzi',
        'order_value' => 'Valoare comenzi',
        'order_rate' => 'Rată de comandă',
        'landing_path' => 'Pagină de destinație',
    ],

    'order' => [
        'traffic_source' => 'Sursă de trafic',
        'source' => 'Sursă',
        'campaign' => 'Campanie',
        'referrer' => 'Site de proveniență',
        'landing_page' => 'Pagină de destinație',
        'visited_at' => 'Începutul vizitei',
    ],

    'sources' => [
        'direct' => 'Direct',
    ],

    'mediums' => [
        'organic' => 'Căutare organică',
        'cpc' => 'Reclamă plătită',
        'social' => 'Rețele sociale',
        'referral' => 'Recomandare',
        'email' => 'Email',
    ],

    'unknown' => 'Necunoscut',
    'other' => 'Altele',
    'no_data' => 'Nu există date pentru această perioadă',
];
