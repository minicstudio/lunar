<?php

return [
    'page' => [
        'title' => 'Forgalmi források',
        'navigation_label' => 'Forgalmi források',
    ],

    'filters' => [
        'from' => 'Kezdete',
        'to' => 'Vége',
    ],

    'stats' => [
        'visits' => 'Látogatások',
        'carts' => 'Kosarak',
        'carts_description' => 'Forgalmi forrással létrehozott kosarak',
        'orders' => 'Rendelések',
        'orders_description' => 'Ebből :count forgalmi forrással',
        'order_value' => 'Rendelési érték',
        'order_rate' => 'Rendelési arány',
        'order_rate_description' => 'Forgalmi forrással rendelkező rendelések látogatásonként',
    ],

    'widgets' => [
        'visits_by_source' => [
            'heading' => 'Látogatások forrás szerint',
        ],
        'visits_over_time' => [
            'heading' => [
                'day' => 'Napi látogatások',
                'week' => 'Heti látogatások',
                'month' => 'Havi látogatások',
            ],
            'series' => 'Látogatások',
        ],
        'funnel' => [
            'heading' => 'Kosarak és rendelések forrás szerint',
        ],
        'landing_pages' => [
            'heading' => 'Érkezési oldalak',
        ],
    ],

    'columns' => [
        'source' => 'Forrás',
        'medium' => 'Csatorna',
        'visits' => 'Látogatások',
        'carts' => 'Kosarak',
        'orders' => 'Rendelések',
        'order_value' => 'Rendelési érték',
        'order_rate' => 'Rendelési arány',
        'landing_path' => 'Érkezési oldal',
    ],

    'order' => [
        'traffic_source' => 'Forgalmi forrás',
        'source' => 'Forrás',
        'campaign' => 'Kampány',
        'referrer' => 'Hivatkozó webhely',
        'landing_page' => 'Érkezési oldal',
        'visited_at' => 'Látogatás kezdete',
    ],

    'sources' => [
        'direct' => 'Közvetlen',
    ],

    'mediums' => [
        'organic' => 'Organikus keresés',
        'cpc' => 'Fizetett hirdetés',
        'social' => 'Közösségi média',
        'referral' => 'Hivatkozó oldal',
        'email' => 'E-mail',
    ],

    'unknown' => 'Ismeretlen',
    'other' => 'Egyéb',
    'no_data' => 'Nincs adat ebben az időszakban',
];
