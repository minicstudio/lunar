<?php

return [

    'label' => 'Захиалга',

    'plural_label' => 'Захиалгууд',

    'statuses' => [
        'split' => 'Хуваагдсан',
    ],

    'breadcrumb' => [
        'manage' => 'Удирдах',
    ],

    'tabs' => [
        'all' => 'Бүгд',
    ],

    'transactions' => [
        'capture' => 'Төлсөн',
        'intent' => 'Нөөц',
        'refund' => 'Буцаасан',
        'failed' => 'Амжилтгүй',
    ],

    'table' => [
        'status' => [
            'label' => 'Статус',
        ],
        'reference' => [
            'label' => 'Лавлагаа',
        ],
        'customer_reference' => [
            'label' => 'Харилцагчийн лавлагаа',
        ],
        'customer' => [
            'label' => 'Харилцагч',
        ],
        'tags' => [
            'label' => 'Таг',
            'no_options_message' => 'Таг олдсонгүй',
        ],
        'postcode' => [
            'label' => 'Шуудангийн код',
        ],
        'email' => [
            'label' => 'Имэйл',
            'copy_message' => 'Имэйл хаяг хуулагдсан',
        ],
        'phone' => [
            'label' => 'Утас',
        ],
        'total' => [
            'label' => 'Нийт',
        ],
        'date' => [
            'label' => 'Он сар өдөр',
        ],
        'new_customer' => [
            'label' => 'Харилцагчийн төрөл',
        ],
        'payment_type' => [
            'label' => 'Төлбөрийн төрөл',
            'options' => [
                'offline' => 'Офлайн',
                'card' => 'Карт',
            ],
        ],
        'total_from' => [
            'label' => 'Нийтээс',
        ],
        'total_to' => [
            'label' => 'Нийт хүртэл',
        ],
        'placed_after' => [
            'label' => 'Дараа захиалсан',
        ],
        'placed_before' => [
            'label' => 'Өмнө захиалсан',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'Нэр',
            ],
            'last_name' => [
                'label' => 'Овог',
            ],
            'line_one' => [
                'label' => 'Хаяг мөр 1',
            ],
            'line_two' => [
                'label' => 'Хаяг мөр 2',
            ],
            'line_three' => [
                'label' => 'Хаяг мөр 3',
            ],
            'company_name' => [
                'label' => 'Компаний нэр',
            ],
            'tax_identifier' => [
                'label' => 'Татварын таниулагч',
            ],
            'contact_phone' => [
                'label' => 'Утас',
            ],
            'contact_email' => [
                'label' => 'Имэйл хаяг',
            ],
            'city' => [
                'label' => 'Хот',
            ],
            'state' => [
                'label' => 'Аймаг/Муж',
            ],
            'postcode' => [
                'label' => 'Шуудангийн код',
            ],
            'country_id' => [
                'label' => 'Улс',
            ],
        ],

        'reference' => [
            'label' => 'Лавлагаа',
        ],
        'status' => [
            'label' => 'Статус',
        ],
        'transaction' => [
            'label' => 'Гүйлгээ',
        ],
        'amount' => [
            'label' => 'Дүн',
            'hint' => [
                'less_than_total' => 'Та нийт гүйлгээнээс бага дүн төлөх гэж байна',
            ],
        ],

        'notes' => [
            'label' => 'Тэмдэглэл',
        ],
        'confirm' => [
            'label' => 'Баталгаажуулах',
            'alert' => 'Баталгаа шаардлагатай',
            'hint' => [
                'capture' => 'Энэ төлбөрийг баталгаажуулахыг хүсэж буйгаа батална уу',
                'refund' => 'Энэ дүнг буцаахыг баталгаажуулна уу.',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Тэмдэглэл',
            'placeholder' => 'Энэ захиалгад тэмдэглэл байхгүй',
        ],
        'delivery_instructions' => [
            'label' => 'Хүргэлтийн заавар',
        ],
        'shipping_total' => [
            'label' => 'Хүргэлтийн нийт',
        ],
        'paid' => [
            'label' => 'Төлсөн',
        ],
        'refund' => [
            'label' => 'Буцаах',
        ],
        'unit_price' => [
            'label' => 'Нэгж үнэ',
        ],
        'quantity' => [
            'label' => 'Тоо хэмжээ',
        ],
        'sub_total' => [
            'label' => 'Дэд нийт',
        ],
        'discount_total' => [
            'label' => 'Хөнгөлөлтийн нийт',
        ],
        'total' => [
            'label' => 'Нийт',
        ],
        'preorder' => [
            'message' => 'Энэ бүтээгдэхүүн зөвхөн урьдчилсан захиалгаар авах боломжтой.',
        ],
        'current_stock_level' => [
            'message' => 'Одоогийн агуулахын нөөц: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'захиалга өгсөн үед: :count',
        ],
        'status' => [
            'label' => 'Статус',
        ],
        'reference' => [
            'label' => 'Лавлагаа',
        ],
        'customer_reference' => [
            'label' => 'Харилцагчийн лавлагаа',
        ],
        'channel' => [
            'label' => 'Суваг',
        ],
        'date_created' => [
            'label' => 'Үүсгэсэн огноо',
        ],
        'date_placed' => [
            'label' => 'Захиалсан огноо',
        ],
        'new_returning' => [
            'label' => 'Шинэ / Дахин ирсэн',
        ],
        'new_customer' => [
            'label' => 'Шинэ харилцагч',
        ],
        'returning_customer' => [
            'label' => 'Дахин ирсэн харилцагч',
        ],
        'shipping_address' => [
            'label' => 'Хүргэлтийн хаяг',
        ],
        'billing_address' => [
            'label' => 'Төлбөрийн хаяг',
        ],
        'address_not_set' => [
            'label' => 'Хаяг тохируулаагүй',
        ],
        'billing_matches_shipping' => [
            'label' => 'Хүргэлтийн хаягтай адил',
        ],
        'additional_info' => [
            'label' => 'Нэмэлт мэдээлэл',
        ],
        'no_additional_info' => [
            'label' => 'Нэмэлт мэдээлэл байхгүй',
        ],
        'shipping_details' => [
            'label' => 'Хүргэлтийн мэдээлэл',
        ],
        'billing_details' => [
            'label' => 'Төлбөрийн мэдээлэл',
        ],
        'meta' => [
            'shippingType' => 'Хүргэлтийн төрөл',
            'shipping_zone' => 'Хүргэлтийн бүс',
            'awb' => 'AWB дугаар',
            'payment_option' => 'Төлбөрийн хэлбэр',
            'payment_type' => 'Төлбөрийн төрөл',
            'isBillingSameAsShipping' => 'Нэхэмжлэхийн хаяг хүргэлтийн хаягтай ижил',
            'billing_series' => 'Нэхэмжлэхийн цуврал',
            'billing_number' => 'Нэхэмжлэхийн дугаар',
            'lunar_invoice_generated_at' => 'Lunar нэхэмжлэх үүсгэсэн',
            'invoice_emailed_at' => 'Нэхэмжлэх имэйлээр илгээсэн',
        ],
        'shipping_types' => [
            'courier' => 'Курьер',
            'locker' => 'Илгээмжийн автомат',
            'pickup' => 'Биечлэн авах',
        ],
        'payment_methods' => [
            'offline' => 'Офлайн',
            'cash-in-hand' => 'Бэлэн мөнгө',
            'cash-on-delivery' => 'Хүлээн авахдаа төлөх',
            'bank-transfer' => 'Банкны шилжүүлэг',
            'stripe-card' => 'Кредит карт',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'Онлайн картын төлбөр',
        ],
        'tags' => [
            'label' => 'Таг',
        ],
        'timeline' => [
            'label' => 'Цаг хугацаа',
        ],
        'transactions' => [
            'label' => 'Гүйлгээнүүд',
            'placeholder' => 'Гүйлгээ байхгүй',
        ],
        'alert' => [
            'requires_capture' => 'Энэ захиалгын төлбөрийг баталгаажуулах шаардлагатай байна.',
            'partially_refunded' => 'Энэ захиалгад хэсэгчлэн буцаах үйлдэл хийгдсэн.',
            'refunded' => 'Энэ захиалга буцаагдсан.',
            'product_line_gates' => [
                'paid' => 'Төлөгдсөн: энэ захиалгыг өөрчлөх боломжгүй, учир нь төлөгдсөн.',
                'erp' => 'ERP: энэ захиалгыг өөрчлөх боломжгүй, учир нь ERP рүү илгээсэн.',
                'invoice' => 'Нэхэмжлэх: энэ захиалгыг өөрчлөх боломжгүй, учир нь нэхэмжлэх байна.',
                'awb' => 'AWB: энэ захиалгыг өөрчлөх боломжгүй, учир нь AWB байна.',
                'split' => 'Split: this order has been split and cannot be modified.',
            ],
            'split_erp_warning' => 'This order may already have been sent to the ERP. Splitting it will not update that external order.',
        ],
        'split_from' => [
            'label' => 'Split from',
        ],
        'split_into' => [
            'label' => 'Split into',
        ],
    ],

    'action' => [
        'bulk_update_status' => [
            'label' => 'Статус шинэчлэх',
            'notification' => 'Захиалгын статус шинэчлэгдсэн',
        ],
        'update_status' => [
            'label' => 'Статус шинэчлэх',
            'notification' => 'Захиалгын статус шинэчлэгдсэн',
            'new_status' => [
                'label' => 'Шинэ статус',
            ],
            'additional_content' => [
                'label' => 'Нэмэлт контент',
            ],
            'additional_email_recipient' => [
                'label' => 'Нэмэлт имэйл хүлээн авагч',
                'placeholder' => 'сонголттой',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'PDF татах',
            'notification' => 'Захиалгын PDF татаж байна',
        ],
        'edit_address' => [
            'label' => 'Засах',
            'notification' => [
                'error' => 'Алдаа',
                'billing_address' => [
                    'saved' => 'Төлбөрийн хаяг хадгалагдсан',
                ],
                'shipping_address' => [
                    'saved' => 'Хүргэлтийн хаяг хадгалагдсан',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Засах',
            'form' => [
                'tags' => [
                    'label' => 'Таг',
                    'helper_text' => 'Тагыг Enter, Tab эсвэл таслал (,) дарж тусгаарлана уу',
                ],
            ],
        ],
        'capture_payment' => [
            'label' => 'Төлбөр баталгаажуулах',
            'notification' => [
                'error' => 'Төлбөр баталгаажуулахад асуудал гарлаа',
                'success' => 'Төлбөр амжилттай баталгаажлаа',
            ],
        ],
        'refund_payment' => [
            'label' => 'Буцаах',
            'notification' => [
                'error' => 'Буцаах явцад асуудал гарсан',
                'success' => 'Амжилттай буцаасан',
            ],
        ],
        'add_product' => [
            'label' => 'Бүтээгдэхүүн нэмэх',
            'product' => [
                'label' => 'Бүтээгдэхүүн',
            ],
            'variant' => [
                'label' => 'Хувилбар',
            ],
            'quantity' => [
                'label' => 'Тоо',
            ],
            'notification' => 'Бүтээгдэхүүн нэмэгдлээ',
        ],
        'update_quantity' => [
            'label' => 'Тоог шинэчлэх',
            'quantity' => [
                'label' => 'Тоо',
            ],
            'notification' => 'Тоо шинэчлэгдлээ',
        ],
        'remove_product' => [
            'label' => 'Бүтээгдэхүүн хасах',
            'confirmation' => 'Энэ бүтээгдэхүүнийг захиалгаас хасах уу?',
            'notification' => 'Бүтээгдэхүүн хасагдлаа',
        ],
        'product_line_edit' => [
            'last_line' => 'Захиалга дор хаяж нэг бүтээгдэхүүн үлдээх ёстой.',
            'unauthorized' => 'Та энэ захиалгын бүтээгдэхүүнийг өөрчилж чадахгүй.',
            'cart' => 'Энэ захиалгад сагс байхгүй тул бүтээгдэхүүнийг өөрчлөх боломжгүй.',
            'line' => 'Тухайн бүтээгдэхүүний мөр олдсонгүй.',
            'purchasable' => 'Тухайн хувилбар олдсонгүй.',
            'shipping' => 'Хүргэлтийн сонголтыг үнэлэх боломжгүй тул захиалга хэвээр үлдлээ.',
            'locked' => 'Энэ захиалгыг цаашид засах боломжгүй.',
            'not_purchasable' => 'Энэ хувилбарыг худалдан авах боломжгүй.',
            'out_of_stock' => 'Энэ хувилбар нөөцгүй байна.',
            'insufficient_stock' => 'Энэ тоонд хүрэлцэх нөөц байхгүй.',
            'missing_price' => 'Энэ хувилбар захиалгын валютаар үнэгүй байна.',
        ],
        'split_order' => [
            'label' => 'Split order',
            'heading' => 'Split order',
            'helper' => 'Assign every product to one order. The preview on the right shows that order\'s products, total, shipping, and payment.',
            'products' => 'Products',
            'preview' => 'Preview',
            'empty_preview' => 'No products are assigned to this order.',
            'new_order' => 'New order',
            'move_to_order' => 'Move to order:',
            'divide_quantity' => 'Divide quantity',
            'price' => 'Price',
            'price_changed' => 'The price of this product has changed since the order was placed.',
            'original_price' => 'Original price',
            'current_price' => 'Current price',
            'shipping' => 'Shipping',
            'shipping_unavailable' => 'Automatic pricing was unavailable. Enter a shipping cost.',
            'payment_method' => 'Payment method',
            'order_total' => 'Order total',
            'coupon' => 'Coupon',
            'loyalty' => 'Loyalty redemption',
            'do_not_apply' => 'Do not apply',
            'confirm' => 'Split order',
            'notification' => 'Order split',
            'invalid' => 'The split is not ready to confirm.',
            'gates' => [
                'closed' => 'This order can no longer be split.',
                'paid' => 'This order has been paid and cannot be split.',
                'invoice' => 'This order has an invoice and cannot be split.',
                'awb' => 'This order has an AWB and cannot be split.',
                'erp' => 'This order has been sent to the ERP and cannot be split.',
                'stock' => 'Stock was already taken for this order. Splitting it does not take stock again.',
                'unauthorized' => 'You cannot split this order.',
                'unassigned' => 'Assign every unit before confirming.',
                'assigned_twice' => 'A unit is assigned to more than one order.',
                'lines_mismatch' => 'The assignment does not match the order.',
                'too_few_targets' => 'A split needs at least two orders.',
                'negative_shipping' => 'Shipping cannot be negative.',
                'coupon' => 'Choose where the coupon goes.',
                'loyalty' => 'Choose where the loyalty redemption goes.',
                'loyalty_spent' => 'Loyalty points were already spent, so they must stay on one order.',
                'loyalty_cannot_accept' => 'The chosen order cannot take all of the loyalty points that were already spent.',
                'shipping_required' => 'Enter a shipping cost for every order.',
                'payment_required' => 'Choose a payment method for every order.',
            ],
        ],
    ],

];
