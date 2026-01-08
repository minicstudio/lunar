<?php

return [
    'shipping_methods' => [
        'customer_groups' => [
            'description' => 'Asocia grupos de clientes a este método de envío para determinar su disponibilidad.',
        ],
        'customer_types' => [
            'heading' => 'Adjuntar Tipo de Cliente',
            'title' => 'Tipo de Cliente',
            'description' => 'Adjunta tipos de clientes (físico/jurídico) para este método de envío.',
        ],
    ],
    'shipping_rates' => [
        'title_plural' => 'Tarifas de Envío',
        'actions' => [
            'create' => [
                'label' => 'Crear Tarifa de Envío',
            ],
            'delete' => [
                'modal' => [
                    'heading' => 'Eliminar tarifa de envío',
                ],
            ],
            'edit' => [
                'modal' => [
                    'heading' => 'Editar tarifa de envío',
                ],
            ],
        ],
        'notices' => [
            'prices_incl_tax' => 'Todos los precios incluyen impuestos, que se tendrán en cuenta al calcular el gasto mínimo.',
            'prices_excl_tax' => 'Todos los precios excluyen impuestos, el gasto mínimo se basará en el subtotal del carrito.',
        ],
        'form' => [
            'shipping_method_id' => [
                'label' => 'Método de Envío',
            ],
            'price' => [
                'label' => 'Precio',
            ],
            'prices' => [
                'label' => 'Desglose de Precios',
                'repeater' => [
                    'customer_group_id' => [
                        'label' => 'Grupo de Clientes',
                        'placeholder' => 'Cualquiera',
                    ],
                    'currency_id' => [
                        'label' => 'Moneda',
                    ],
                    'min_spend' => [
                        'label' => 'Gasto Mín.',
                    ],
                    'min_weight' => [
                        'label' => 'Peso Mín.',
                    ],
                    'max_weight' => [
                        'label' => 'Peso Máx.',
                    ],
                    'price' => [
                        'label' => 'Precio',
                    ],
                ],
            ],
        ],
        'table' => [
            'shipping_method' => [
                'label' => 'Método de Envío',
            ],
            'price' => [
                'label' => 'Precio',
            ],
            'price_breaks_count' => [
                'label' => 'Desglose de Precios',
            ],
        ],
    ],
    'exclusions' => [
        'title_plural' => 'Exclusiones de Envío',
        'form' => [
            'purchasable' => [
                'label' => 'Producto',
            ],
        ],
        'actions' => [
            'create' => [
                'label' => 'Agregar lista de exclusión de envío',
                'modal' => [
                    'heading' => 'Agregar exclusión de envío',
                ],
            ],
            'delete' => [
                'modal' => [
                    'heading' => 'Eliminar exclusión de envío',
                ],
                'bulk' => [
                    'modal' => [
                        'heading' => 'Eliminar exclusiones de envío seleccionadas',
                    ],
                ],
            ],
            'edit' => [
                'modal' => [
                    'heading' => 'Editar exclusión de envío',
                ],
            ],
            'attach' => [
                'label' => 'Agregar lista de exclusión',
                'modal' => [
                    'heading' => 'Adjuntar lista de exclusión',
                ],
            ],
            'detach' => [
                'label' => 'Eliminar',
                'modal' => [
                    'heading' => 'Desprender lista de exclusión',
                ],
            ],
        ],
    ],
];
