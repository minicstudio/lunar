<?php

return [

    'label' => 'Bộ sưu tập',

    'plural_label' => 'Bộ sưu tập',

    'form' => [
        'name' => [
            'label' => 'Tên',
        ],
    ],

    'pages' => [
        'children' => [
            'label' => 'Bộ sưu tập con',
            'actions' => [
                'create_child' => [
                    'label' => 'Tạo bộ sưu tập con',
                    'name' => [
                        'label' => 'Tên',
                    ],
                ],
            ],
            'table' => [
                'children_count' => [
                    'label' => 'Số lượng con',
                ],
                'name' => [
                    'label' => 'Tên',
                ],
            ],
        ],
        'edit' => [
            'label' => 'Thông tin cơ bản',
            'actions' => [
                'delete' => [
                    'select' => 'Bộ sưu tập mục tiêu',
                    'helper_text' => 'Chọn bộ sưu tập mà các phần tử con của bộ sưu tập này sẽ được chuyển đến.'
                ],
            ]
        ],
        'products' => [
            'label' => 'Sản phẩm',
            'actions' => [
                'attach' => [
                    'label' => 'Thêm sản phẩm',
                    'select' => 'Sản phẩm',
                ],
                'detach' => [
                    'modal' => [
                        'heading' => 'Tách sản phẩm',
                    ]
                ],
            ],
        ],
    ],
    'nested_set_item' => [
        'more_actions' => 'Thêm hành động',
    ],
];
