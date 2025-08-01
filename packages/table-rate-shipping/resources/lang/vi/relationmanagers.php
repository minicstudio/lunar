<?php

return [
    'shipping_methods' => [
        'customer_groups' => [
            'description' => 'Liên kết nhóm khách hàng với phương thức vận chuyển này để xác định tính khả dụng của nó.',
        ],
    ],
    'shipping_rates' => [
        'title_plural' => 'Phí vận chuyển',
        'actions' => [
            'create' => [
                'label' => 'Tạo phí vận chuyển',
            ],
            'delete' => [
                'modal' => [
                    'heading' => 'Xóa giá vận chuyển',
                ],
            ],
            'edit' => [
                'modal' => [
                    'heading' => 'Chỉnh sửa giá vận chuyển',
                ],
            ],
        ],
        'notices' => [
            'prices_incl_tax' => 'Tất cả giá đã bao gồm thuế, điều này sẽ được xem xét khi tính toán chi tiêu tối thiểu.',
            'prices_excl_tax' => 'Tất cả giá chưa bao gồm thuế, chi tiêu tối thiểu sẽ dựa trên tổng phụ giỏ hàng.',
        ],
        'form' => [
            'shipping_method_id' => [
                'label' => 'Phương thức vận chuyển',
            ],
            'price' => [
                'label' => 'Giá',
            ],
            'prices' => [
                'label' => 'Mức giá',
                'repeater' => [
                    'customer_group_id' => [
                        'label' => 'Nhóm khách hàng',
                        'placeholder' => 'Bất kỳ',
                    ],
                    'currency_id' => [
                        'label' => 'Tiền tệ',
                    ],
                    'min_spend' => [
                        'label' => 'Chi tiêu T.thiểu',
                    ],
                    'min_weight' => [
                        'label' => 'K.lượng T.thiểu',
                    ],
                    'price' => [
                        'label' => 'Giá',
                    ],
                ],
            ],
        ],
        'table' => [
            'shipping_method' => [
                'label' => 'Phương thức vận chuyển',
            ],
            'price' => [
                'label' => 'Giá',
            ],
            'price_breaks_count' => [
                'label' => 'Mức giá',
            ],
        ],
    ],
    'exclusions' => [
        'title_plural' => 'Danh sách loại trừ vận chuyển',
        'form' => [
            'purchasable' => [
                'label' => 'Sản phẩm',
            ],
        ],
        'actions' => [
            'create' => [
                'label' => 'Thêm danh sách loại trừ vận chuyển',
                'modal' => [
                    'heading' => 'Thêm loại trừ vận chuyển',
                ],
            ],
            'delete' => [
                'modal' => [
                    'heading' => 'Xóa loại trừ vận chuyển',
                ],
                'bulk' => [
                    'modal' => [
                        'heading' => 'Xóa loại trừ vận chuyển đã chọn',
                    ],
                ],
            ],
            'edit' => [
                'modal' => [
                    'heading' => 'Chỉnh sửa loại trừ vận chuyển',
                ],
            ],
            'attach' => [
                'label' => 'Thêm danh sách loại trừ',
                'modal' => [
                    'heading' => 'Đính kèm danh sách loại trừ',
                ],
            ],
            'detach' => [
                'label' => 'Xóa',
                'modal' => [
                    'heading' => 'Tách danh sách loại trừ',
                ],
            ],
        ],
    ],
];
