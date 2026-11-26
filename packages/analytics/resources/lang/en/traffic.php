<?php

return [
    'page' => [
        'title' => 'Traffic sources',
        'navigation_label' => 'Traffic sources',
    ],

    'stats' => [
        'visits' => 'Visits',
        'carts' => 'Carts',
        'carts_description' => 'Carts created with a traffic source',
        'orders' => 'Orders',
        'orders_description' => ':count with a traffic source',
        'order_value' => 'Order value',
        'order_rate' => 'Order rate',
        'order_rate_description' => 'Orders with a traffic source per visit',
        'note' => 'Note',
        'order_rate_crawler_note' => 'Crawlers that are not recognised count as visits and can lower this rate.',
        'cart_order_rate' => 'Cart order rate',
        'cart_order_rate_description' => 'Orders with a traffic source per cart with a traffic source',
    ],

    'widgets' => [
        'visits_by_source' => [
            'heading' => 'Visits by source',
        ],
        'visits_over_time' => [
            'heading' => [
                'day' => 'Visits per day',
                'week' => 'Visits per week',
                'month' => 'Visits per month',
            ],
            'series' => 'Visits',
        ],
        'funnel' => [
            'heading' => 'Carts and orders by source',
        ],
        'landing_pages' => [
            'heading' => 'Landing pages',
        ],
    ],

    'columns' => [
        'source' => 'Source',
        'medium' => 'Medium',
        'visits' => 'Visits',
        'carts' => 'Carts',
        'orders' => 'Orders',
        'order_value' => 'Order value',
        'order_rate' => 'Order rate',
        'landing_path' => 'Landing page',
    ],

    'order' => [
        'traffic_source' => 'Traffic source',
        'source' => 'Source',
        'campaign' => 'Campaign',
        'referrer' => 'Referrer',
        'landing_page' => 'Landing page',
        'visited_at' => 'Visit started',
    ],

    'sources' => [
        'direct' => 'Direct',
    ],

    'mediums' => [
        'organic' => 'Organic search',
        'cpc' => 'Paid ad',
        'social' => 'Social',
        'referral' => 'Referral',
        'email' => 'Email',
    ],

    'unknown' => 'Unknown',
    'other' => 'Other',
    'no_data' => 'No data for this period',
];
