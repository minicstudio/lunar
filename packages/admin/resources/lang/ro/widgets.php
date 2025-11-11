<?php

return [
    'dashboard' => [
        'orders' => [
            'order_stats_overview' => [
                'stat_one' => [
                    'label' => 'Comenzi astăzi',
                    'increase' => ':percentage% creștere față de :count ieri',
                    'decrease' => ':percentage% scădere față de :count ieri',
                    'neutral' => 'Fără schimbare față de ieri',
                ],
                'stat_two' => [
                    'label' => 'Comenzi ultimele 7 zile',
                    'increase' => ':percentage% creștere față de :count perioada anterioară',
                    'decrease' => ':percentage% scădere față de :count perioada anterioară',
                    'neutral' => 'Fără schimbare față de perioada anterioară',
                ],
                'stat_three' => [
                    'label' => 'Comenzi ultimele 30 zile',
                    'increase' => ':percentage% creștere față de :count perioada anterioară',
                    'decrease' => ':percentage% scădere față de :count perioada anterioară',
                    'neutral' => 'Fără schimbare față de perioada anterioară',
                ],
                'stat_four' => [
                    'label' => 'Vânzări astăzi',
                    'increase' => ':percentage% creștere față de :total ieri',
                    'decrease' => ':percentage% scădere față de :total ieri',
                    'neutral' => 'Fără schimbare față de ieri',
                ],
                'stat_five' => [
                    'label' => 'Vânzări ultimele 7 zile',
                    'increase' => ':percentage% creștere față de :total perioada anterioară',
                    'decrease' => ':percentage% scădere față de :total perioada anterioară',
                    'neutral' => 'Fără schimbare față de perioada anterioară',
                ],
                'stat_six' => [
                    'label' => 'Vânzări ultimele 30 zile',
                    'increase' => ':percentage% creștere față de :total perioada anterioară',
                    'decrease' => ':percentage% scădere față de :total perioada anterioară',
                    'neutral' => 'Fără schimbare față de perioada anterioară',
                ],
            ],
            'order_totals_chart' => [
                'heading' => 'Totaluri comenzi pentru ultimul an',
                'series_one' => [
                    'label' => 'Această perioadă',
                ],
                'series_two' => [
                    'label' => 'Perioada anterioară',
                ],
                'yaxis' => [
                    'label' => 'Cifră de afaceri :currency',
                ],
            ],
            'order_sales_chart' => [
                'heading' => 'Raport Comenzi / Vânzări',
                'series_one' => [
                    'label' => 'Comenzi',
                ],
                'series_two' => [
                    'label' => 'Venituri',
                ],
                'yaxis' => [
                    'series_one' => [
                        'label' => '# Comenzi',
                    ],
                    'series_two' => [
                        'label' => 'Valoare totală',
                    ],
                ],
            ],
            'average_order_value' => [
                'heading' => 'Valoare medie comandă',
            ],
            'new_returning_customers' => [
                'heading' => 'Clienți noi vs. reveniți',
                'series_one' => [
                    'label' => 'Clienți noi',
                ],
                'series_two' => [
                    'label' => 'Clienți reveniți',
                ],
                'yaxis' => [
                    'label' => '# Clienți',
                ],
            ],
            'popular_products' => [
                'heading' => 'Cele mai vândute (ultimele 12 luni)',
                'description' => 'Aceste cifre se bazează pe numărul de ori când un produs apare într-o comandă, nu pe cantitatea comandată.',
                'table' => [
                    'description' => 'Descriere',
                    'identifier' => 'Identificator',
                    'quantity' => 'Cantitate',
                    'sub_total' => 'Subtotal',
                ],
            ],
            'latest_orders' => [
                'heading' => 'Ultimele comenzi',
            ],
            'orders_by_status' => [
                'heading' => 'Comenzi pe status',
                'yaxis' => [
                    'count' => [
                        'label' => 'Număr de comenzi',
                    ],
                ],
                'tooltip' => [
                    'orders' => 'comenzi',
                ],
            ],
            'order_values_by_status' => [
                'heading' => 'Valori comenzi pe status',
                'yaxis' => [
                    'label' => 'Valoare comandă',
                ],
            ],
        ],
    ],
    'customer' => [
        'stats_overview' => [
            'total_orders' => [
                'label' => 'Total comenzi',
            ],
            'avg_spend' => [
                'label' => 'Cheltuială medie',
            ],
            'total_spend' => [
                'label' => 'Cheltuială totală',
            ],
        ],
    ],
    'variant_switcher' => [
        'label' => 'Schimbă variantă',
        'table' => [
            'sku' => [
                'label' => 'SKU',
            ],
            'values' => [
                'label' => 'Valori',
            ],
        ],
    ],
];

