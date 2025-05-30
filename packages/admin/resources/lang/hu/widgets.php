<?php

return [
    'dashboard' => [
        'orders' => [
            'order_stats_overview' => [
                'stat_one' => [
                    'label' => 'Mai rendelések',
                    'increase' => ':percentage% növekedés a tegnapi :count-hoz képest',
                    'decrease' => ':percentage% csökkenés a tegnapi :count-hoz képest',
                    'neutral' => 'Nincs változás a tegnapihoz képest',
                ],
                'stat_two' => [
                    'label' => 'Rendelések az elmúlt 7 napban',
                    'increase' => ':percentage% növekedés az előző időszak :count-jához képest',
                    'decrease' => ':percentage% csökkenés az előző időszak :count-jához képest',
                    'neutral' => 'Nincs változás az előző időszakhoz képest',
                ],
                'stat_three' => [
                    'label' => 'Rendelések az elmúlt 30 napban',
                    'increase' => ':percentage% növekedés az előző időszak :count-jához képest',
                    'decrease' => ':percentage% csökkenés az előző időszak :count-jához képest',
                    'neutral' => 'Nincs változás az előző időszakhoz képest',
                ],
                'stat_four' => [
                    'label' => 'Eladások ma',
                    'increase' => ':percentage% növekedés a tegnapi :total-hoz képest',
                    'decrease' => ':percentage% csökkenés a tegnapi :total-hoz képest',
                    'neutral' => 'Nincs változás a tegnapihoz képest',
                ],
                'stat_five' => [
                    'label' => 'Eladások az elmúlt 7 napban',
                    'increase' => ':percentage% növekedés az előző időszak :total-jához képest',
                    'decrease' => ':percentage% csökkenés az előző időszak :total-jához képest',
                    'neutral' => 'Nincs változás az előző időszakhoz képest',
                ],
                'stat_six' => [
                    'label' => 'Eladások az elmúlt 30 napban',
                    'increase' => ':percentage% növekedés az előző időszak :total-jához képest',
                    'decrease' => ':percentage% csökkenés az előző időszak :total-jához képest',
                    'neutral' => 'Nincs változás az előző időszakhoz képest',
                ],
            ],
            'order_totals_chart' => [
                'heading' => 'Rendelés összesítések az elmúlt évben',
                'series_one' => [
                    'label' => 'Aktuális időszak',
                ],
                'series_two' => [
                    'label' => 'Előző időszak',
                ],
                'yaxis' => [
                    'label' => 'Forgalom :currency',
                ],
            ],
            'order_sales_chart' => [
                'heading' => 'Rendelések / Eladások jelentése',
                'series_one' => [
                    'label' => 'Rendelések',
                ],
                'series_two' => [
                    'label' => 'Bevétel',
                ],
                'yaxis' => [
                    'series_one' => [
                        'label' => 'Rendelések száma',
                    ],
                    'series_two' => [
                        'label' => 'Teljes érték',
                    ],
                ],
            ],
            'average_order_value' => [
                'heading' => 'Átlagos rendelési érték',
            ],
            'new_returning_customers' => [
                'heading' => 'Új és visszatérő vásárlók',
                'series_one' => [
                    'label' => 'Új vásárlók',
                ],
                'series_two' => [
                    'label' => 'Visszatérő vásárlók',
                ],
            ],
            'popular_products' => [
                'heading' => 'Legnépszerűbb termékek (utóbbi 12 hónap)',
                'description' => 'Ezek az adatok a termékek előfordulásán alapulnak rendelésekben, nem a rendelt mennyiségen.',
            ],
            'latest_orders' => [
                'heading' => 'Legfrissebb rendelések',
            ],
        ],
    ],
    'customer' => [
        'stats_overview' => [
            'total_orders' => [
                'label' => 'Összes rendelés',
            ],
            'avg_spend' => [
                'label' => 'Átlagos költés',
            ],
            'total_spend' => [
                'label' => 'Teljes költés',
            ],
        ],
    ],
    'variant_switcher' => [
        'label' => 'Válasszon változatot',
        'table' => [
            'sku' => [
                'label' => 'SKU',
            ],
            'values' => [
                'label' => 'Értékek',
            ],
        ],
    ],
];
