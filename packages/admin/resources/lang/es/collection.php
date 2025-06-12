<?php

return [

    'label' => 'Colección',

    'plural_label' => 'Colecciones',

    'form' => [
        'name' => [
            'label' => 'Nombre',
        ],
    ],

    'pages' => [
        'children' => [
            'label' => 'Colecciones Hijas',
            'actions' => [
                'create_child' => [
                    'label' => 'Crear Colección Hija',
                    'name' => [
                        'label' => 'Nombre',
                    ],
                ],
            ],
            'table' => [
                'children_count' => [
                    'label' => 'N.º Hijas',
                ],
                'name' => [
                    'label' => 'Nombre',
                ],
            ],
        ],
        'edit' => [
            'label' => 'Información Básica',
            'actions' => [
                'delete' => [
                    'select' => 'Colección Objetivo',
                    'helper_text' => 'Seleccione a qué colección se transferirán los elementos secundarios de esta colección.'
                ],
            ],
        ],
        'products' => [
            'label' => 'Productos',
            'actions' => [
                'attach' => [
                    'label' => 'Asociar Producto',
                    'select' => 'Producto',
                ],
                'detach' => [
                    'modal' => [
                        'heading' => 'Separar producto',
                    ]
                ],
            ],
        ],
    ],
    'nested_set_item' => [
        'more_actions' => 'Más acciones',
    ],
];
