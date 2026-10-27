<?php

return [
    'label' => 'Đơn hàng',

    'plural_label' => 'Đơn hàng',

    'breadcrumb' => [
        'manage' => 'Quản lý',
    ],

    'tabs' => [
        'all' => 'Tất cả',
    ],

    'transactions' => [
        'capture' => 'Đã thu tiền',
        'intent' => 'Chờ thanh toán',
        'refund' => 'Đã hoàn tiền',
        'failed' => 'Thất bại',
    ],

    'table' => [
        'status' => [
            'label' => 'Trạng thái',
        ],
        'reference' => [
            'label' => 'Mã tham chiếu',
        ],
        'customer_reference' => [
            'label' => 'Mã khách hàng',
        ],
        'customer' => [
            'label' => 'Khách hàng',
        ],
        'tags' => [
            'label' => 'Thẻ',
            'no_options_message' => 'Không tìm thấy thẻ',
        ],
        'postcode' => [
            'label' => 'Mã bưu điện',
        ],
        'email' => [
            'label' => 'Email',
            'copy_message' => 'Đã sao chép địa chỉ email',
        ],
        'phone' => [
            'label' => 'Số điện thoại',
        ],
        'total' => [
            'label' => 'Tổng cộng',
        ],
        'date' => [
            'label' => 'Ngày',
        ],
        'new_customer' => [
            'label' => 'Loại khách hàng',
        ],
        'payment_type' => [
            'label' => 'Loại thanh toán',
            'options' => [
                'offline' => 'Ngoại tuyến',
                'card' => 'Thẻ',
            ],
        ],
        'total_from' => [
            'label' => 'Tổng từ',
        ],
        'total_to' => [
            'label' => 'Tổng đến',
        ],
        'placed_after' => [
            'label' => 'Đặt sau',
        ],
        'placed_before' => [
            'label' => 'Đặt trước',
        ],
        'show_draft_orders' => [
            'label' => 'Hiển thị đơn hàng chưa hoàn tất',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'Tên',
            ],
            'last_name' => [
                'label' => 'Họ',
            ],
            'line_one' => [
                'label' => 'Địa chỉ dòng 1',
            ],
            'line_two' => [
                'label' => 'Địa chỉ dòng 2',
            ],
            'line_three' => [
                'label' => 'Địa chỉ dòng 3',
            ],
            'company_name' => [
                'label' => 'Tên công ty',
            ],
            'contact_phone' => [
                'label' => 'Số điện thoại',
            ],
            'contact_email' => [
                'label' => 'Địa chỉ email',
            ],
            'city' => [
                'label' => 'Thành phố',
            ],
            'state' => [
                'label' => 'Tỉnh/Thành phố',
            ],
            'postcode' => [
                'label' => 'Mã bưu điện',
            ],
            'country_id' => [
                'label' => 'Quốc gia',
            ],
        ],

        'reference' => [
            'label' => 'Mã tham chiếu',
        ],
        'status' => [
            'label' => 'Trạng thái',
        ],
        'transaction' => [
            'label' => 'Giao dịch',
        ],
        'amount' => [
            'label' => 'Số tiền',
            'hint' => [
                'less_than_total' => 'Bạn sắp thu một số tiền ít hơn tổng giá trị giao dịch',
            ],
        ],
        'notes' => [
            'label' => 'Ghi chú',
        ],
        'confirm' => [
            'label' => 'Xác nhận',
            'alert' => 'Yêu cầu xác nhận',
            'hint' => [
                'capture' => 'Vui lòng xác nhận bạn muốn thu tiền thanh toán này',
                'refund' => 'Vui lòng xác nhận bạn muốn hoàn tiền số tiền này',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Ghi chú',
            'placeholder' => 'Không có ghi chú cho đơn hàng này',
        ],
        'delivery_instructions' => [
            'label' => 'Hướng dẫn giao hàng',
        ],
        'shipping_total' => [
            'label' => 'Tổng phí vận chuyển',
        ],
        'paid' => [
            'label' => 'Đã thanh toán',
        ],
        'refund' => [
            'label' => 'Hoàn tiền',
        ],
        'unit_price' => [
            'label' => 'Đơn giá',
        ],
        'quantity' => [
            'label' => 'Số lượng',
        ],
        'sub_total' => [
            'label' => 'Tạm tính',
        ],
        'discount_total' => [
            'label' => 'Tổng giảm giá',
        ],
        'total' => [
            'label' => 'Tổng cộng',
        ],
        'preorder' => [
            'message' => 'Sản phẩm này chỉ có sẵn để đặt hàng trước.',
        ],
        'current_stock_level' => [
            'message' => 'Số lượng tồn kho hiện tại: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'tại thời điểm đặt hàng: :count',
        ],
        'status' => [
            'label' => 'Trạng thái',
        ],
        'reference' => [
            'label' => 'Mã tham chiếu',
        ],
        'customer_reference' => [
            'label' => 'Mã khách hàng',
        ],
        'channel' => [
            'label' => 'Kênh bán hàng',
        ],
        'date_created' => [
            'label' => 'Ngày tạo',
        ],
        'date_placed' => [
            'label' => 'Ngày đặt hàng',
        ],
        'new_returning' => [
            'label' => 'Mới/Quay lại',
        ],
        'new_customer' => [
            'label' => 'Khách hàng mới',
        ],
        'returning_customer' => [
            'label' => 'Khách hàng quay lại',
        ],
        'shipping_address' => [
            'label' => 'Địa chỉ giao hàng',
        ],
        'billing_address' => [
            'label' => 'Địa chỉ thanh toán',
        ],
        'address_not_set' => [
            'label' => 'Chưa có địa chỉ',
        ],
        'billing_matches_shipping' => [
            'label' => 'Giống địa chỉ giao hàng',
        ],
        'additional_info' => [
            'label' => 'Thông tin bổ sung',
        ],
        'no_additional_info' => [
            'label' => 'Không có thông tin bổ sung',
        ],
        'shipping_details' => [
            'label' => 'Chi tiết vận chuyển',
        ],
        'billing_details' => [
            'label' => 'Chi tiết thanh toán',
        ],
        'meta' => [
            'shippingType' => 'Hình thức vận chuyển',
            'shipping_zone' => 'Khu vực vận chuyển',
            'awb' => 'Số AWB',
            'payment_option' => 'Phương thức thanh toán',
            'payment_type' => 'Loại thanh toán',
            'isBillingSameAsShipping' => 'Địa chỉ thanh toán giống địa chỉ giao hàng',
            'billing_series' => 'Ký hiệu hóa đơn',
            'billing_number' => 'Số hóa đơn',
            'lunar_invoice_generated_at' => 'Hóa đơn Lunar được tạo lúc',
            'invoice_emailed_at' => 'Hóa đơn đã gửi email lúc',
        ],
        'shipping_types' => [
            'courier' => 'Chuyển phát nhanh',
            'locker' => 'Tủ nhận hàng',
            'pickup' => 'Nhận tại cửa hàng',
        ],
        'payment_methods' => [
            'offline' => 'Ngoại tuyến',
            'cash-in-hand' => 'Tiền mặt',
            'cash-on-delivery' => 'Thanh toán khi nhận hàng',
            'bank-transfer' => 'Chuyển khoản ngân hàng',
            'stripe-card' => 'Thẻ tín dụng',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'Thanh toán thẻ trực tuyến',
        ],
        'tags' => [
            'label' => 'Thẻ',
        ],
        'timeline' => [
            'label' => 'Dòng thời gian',
        ],
        'transactions' => [
            'label' => 'Giao dịch',
            'placeholder' => 'Không có giao dịch',
        ],
        'alert' => [
            'draft_order' => 'Đơn hàng này chưa được hoàn tất.',
            'requires_capture' => 'Đơn hàng này vẫn cần thu tiền thanh toán',
            'partially_refunded' => 'Đơn hàng này đã được hoàn tiền một phần',
            'refunded' => 'Đơn hàng này đã được hoàn tiền',
            'product_line_gates' => [
                'paid' => 'Đã thanh toán: đơn hàng này không thể sửa vì đã được thanh toán.',
                'erp' => 'ERP: đơn hàng này không thể sửa vì đã được gửi tới ERP.',
                'invoice' => 'Hóa đơn: đơn hàng này không thể sửa vì đã có hóa đơn.',
                'awb' => 'AWB: đơn hàng này không thể sửa vì đã có AWB.',
            ],
        ],
    ],

    'action' => [
        'bulk_update_status' => [
            'label' => 'Cập nhật trạng thái',
            'notification' => 'Đã cập nhật trạng thái đơn hàng',
        ],
        'update_status' => [
            'label' => 'Update Status',
            'notification' => 'Order status updated',
            'new_status' => [
                'label' => 'Trạng thái mới',
            ],
            'additional_content' => [
                'label' => 'Nội dung bổ sung',
            ],
            'additional_email_recipient' => [
                'label' => 'Người nhận email bổ sung',
                'placeholder' => 'tùy chọn',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'Tải PDF',
            'notification' => 'Đang tải PDF đơn hàng',
        ],
        'edit_address' => [
            'label' => 'Chỉnh sửa',
            'notification' => [
                'error' => 'Lỗi',
                'billing_address' => [
                    'saved' => 'Đã lưu địa chỉ thanh toán',
                ],
                'shipping_address' => [
                    'saved' => 'Đã lưu địa chỉ giao hàng',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Chỉnh sửa',
        ],
        'capture_payment' => [
            'label' => 'Thu tiền thanh toán',
            'notification' => [
                'error' => 'Có lỗi khi thu tiền',
                'success' => 'Thu tiền thành công',
            ],
        ],
        'refund_payment' => [
            'label' => 'Hoàn tiền',
            'notification' => [
                'error' => 'Có lỗi khi hoàn tiền',
                'success' => 'Hoàn tiền thành công',
            ],
        ],
        'add_product' => [
            'label' => 'Thêm sản phẩm',
            'product' => [
                'label' => 'Sản phẩm',
            ],
            'variant' => [
                'label' => 'Biến thể',
            ],
            'quantity' => [
                'label' => 'Số lượng',
            ],
            'notification' => 'Đã thêm sản phẩm',
        ],
        'update_quantity' => [
            'label' => 'Cập nhật số lượng',
            'quantity' => [
                'label' => 'Số lượng',
            ],
            'notification' => 'Đã cập nhật số lượng',
        ],
        'remove_product' => [
            'label' => 'Xóa sản phẩm',
            'confirmation' => 'Xóa sản phẩm này khỏi đơn hàng?',
            'notification' => 'Đã xóa sản phẩm',
        ],
        'product_line_edit' => [
            'last_line' => 'Đơn hàng phải giữ ít nhất một sản phẩm.',
            'unauthorized' => 'Bạn không thể thay đổi sản phẩm của đơn hàng này.',
            'cart' => 'Đơn hàng này không có giỏ hàng, nên không thể thay đổi sản phẩm.',
            'line' => 'Không tìm thấy dòng sản phẩm đó.',
            'purchasable' => 'Không tìm thấy biến thể đó.',
            'shipping' => 'Không thể tính giá phương thức vận chuyển, nên đơn hàng được giữ nguyên.',
            'locked' => 'Đơn hàng này không còn có thể chỉnh sửa.',
            'not_purchasable' => 'Biến thể này không thể mua.',
            'out_of_stock' => 'Biến thể này đã hết hàng.',
            'insufficient_stock' => 'Không đủ tồn kho cho số lượng này.',
            'missing_price' => 'Biến thể này không có giá theo tiền tệ của đơn hàng.',
        ],
    ],
];
