<?php

return [

    'label' => 'Order',

    'plural_label' => 'Orders',

    'statuses' => [
        'split' => 'تقسیم‌شده',
    ],

    'breadcrumb' => [
        'manage' => 'Manage',
    ],

    'tabs' => [
        'all' => 'All',
    ],

    'transactions' => [
        'capture' => 'Captured',
        'intent' => 'Intent',
        'refund' => 'Refunded',
        'failed' => 'Failed',
    ],

    'table' => [
        'status' => [
            'label' => 'Status',
        ],
        'reference' => [
            'label' => 'Reference',
        ],
        'customer_reference' => [
            'label' => 'Customer Reference',
        ],
        'customer' => [
            'label' => 'Customer',
        ],
        'tags' => [
            'label' => 'Tags',
            'no_options_message' => 'هیچ برچسبی یافت نشد',
        ],
        'postcode' => [
            'label' => 'Postcode',
        ],
        'email' => [
            'label' => 'Email',
            'copy_message' => 'Email address copied',
        ],
        'phone' => [
            'label' => 'Phone',
        ],
        'total' => [
            'label' => 'Total',
        ],
        'date' => [
            'label' => 'Date',
        ],
        'new_customer' => [
            'label' => 'Customer Type',
        ],
        'payment_type' => [
            'label' => 'Payment type',
            'options' => [
                'offline' => 'Offline',
                'card' => 'Card',
            ],
        ],
        'total_from' => [
            'label' => 'Total from',
        ],
        'total_to' => [
            'label' => 'Total to',
        ],
        'placed_after' => [
            'label' => 'Placed after',
        ],
        'placed_before' => [
            'label' => 'Placed before',
        ],
    ],

    'form' => [
        'address' => [
            'first_name' => [
                'label' => 'First Name',
            ],
            'last_name' => [
                'label' => 'Last Name',
            ],
            'line_one' => [
                'label' => 'Address Line 1',
            ],
            'line_two' => [
                'label' => 'Address Line 2',
            ],
            'line_three' => [
                'label' => 'Address Line 3',
            ],
            'company_name' => [
                'label' => 'Company Name',
            ],
            'tax_identifier' => [
                'label' => 'Tax Identifier',
            ],
            'contact_phone' => [
                'label' => 'Phone',
            ],
            'contact_email' => [
                'label' => 'Email Address',
            ],
            'city' => [
                'label' => 'City',
            ],
            'state' => [
                'label' => 'State / Province',
            ],
            'postcode' => [
                'label' => 'Postal Code',
            ],
            'country_id' => [
                'label' => 'Country',
            ],
        ],

        'reference' => [
            'label' => 'Reference',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'transaction' => [
            'label' => 'Transaction',
        ],
        'amount' => [
            'label' => 'Amount',

            'hint' => [
                'less_than_total' => "You're about to capture an amount less than the total transaction value",
            ],
        ],

        'notes' => [
            'label' => 'Notes',
        ],
        'confirm' => [
            'label' => 'Confirm',

            'alert' => 'Confirmation required',

            'hint' => [
                'capture' => 'Please confirm you want to capture this payment',
                'refund' => 'Please confirm you wish to refund this amount.',
            ],
        ],
    ],

    'infolist' => [
        'notes' => [
            'label' => 'Notes',
            'placeholder' => 'No notes on this order',
        ],
        'delivery_instructions' => [
            'label' => 'Delivery Instructions',
        ],
        'shipping_total' => [
            'label' => 'Shipping Total',
        ],
        'paid' => [
            'label' => 'Paid',
        ],
        'refund' => [
            'label' => 'Refund',
        ],
        'unit_price' => [
            'label' => 'Unit Price',
        ],
        'quantity' => [
            'label' => 'Quantity',
        ],
        'sub_total' => [
            'label' => 'Sub Total',
        ],
        'discount_total' => [
            'label' => 'Discount Total',
        ],
        'total' => [
            'label' => 'Total',
        ],
        'preorder' => [
            'message' => 'این محصول فقط برای پیش‌سفارش در دسترس است.',
        ],
        'current_stock_level' => [
            'message' => 'Current Stock Level: :count',
        ],
        'purchase_stock_level' => [
            'message' => 'at time of ordering: :count',
        ],
        'status' => [
            'label' => 'Status',
        ],
        'reference' => [
            'label' => 'Reference',
        ],
        'customer_reference' => [
            'label' => 'Customer Reference',
        ],
        'channel' => [
            'label' => 'Channel',
        ],
        'date_created' => [
            'label' => 'Date Created',
        ],
        'date_placed' => [
            'label' => 'Date Placed',
        ],
        'new_returning' => [
            'label' => 'New / Returning',
        ],
        'new_customer' => [
            'label' => 'New Customer',
        ],
        'returning_customer' => [
            'label' => 'Returning Customer',
        ],
        'shipping_address' => [
            'label' => 'Shipping Address',
        ],
        'billing_address' => [
            'label' => 'Billing Address',
        ],
        'address_not_set' => [
            'label' => 'No address set',
        ],
        'billing_matches_shipping' => [
            'label' => 'Same as shipping address',
        ],
        'additional_info' => [
            'label' => 'Additional Information',
        ],
        'no_additional_info' => [
            'label' => 'No Additional Information',
        ],
        'shipping_details' => [
            'label' => 'جزئیات ارسال',
        ],
        'billing_details' => [
            'label' => 'جزئیات صورتحساب',
        ],
        'meta' => [
            'shippingType' => 'نوع ارسال',
            'shipping_zone' => 'منطقه ارسال',
            'awb' => 'شماره AWB',
            'payment_option' => 'روش پرداخت',
            'payment_type' => 'نوع پرداخت',
            'isBillingSameAsShipping' => 'آدرس صورتحساب با آدرس ارسال یکسان است',
            'billing_series' => 'سری فاکتور',
            'billing_number' => 'شماره فاکتور',
            'lunar_invoice_generated_at' => 'فاکتور Lunar تولید شده در',
            'invoice_emailed_at' => 'فاکتور ایمیل شده در',
        ],
        'shipping_types' => [
            'courier' => 'پیک',
            'locker' => 'کمد تحویل مرسوله',
            'pickup' => 'تحویل حضوری',
        ],
        'payment_methods' => [
            'offline' => 'آفلاین',
            'cash-in-hand' => 'نقدی',
            'cash-on-delivery' => 'پرداخت در محل',
            'bank-transfer' => 'انتقال بانکی',
            'stripe-card' => 'کارت اعتباری',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'پرداخت آنلاین با کارت',
        ],
        'tags' => [
            'label' => 'Tags',
        ],
        'timeline' => [
            'label' => 'Timeline',
        ],
        'transactions' => [
            'label' => 'Transactions',
            'placeholder' => 'No transactions',
        ],
        'alert' => [
            'requires_capture' => 'This order still requires payment to be captured.',
            'partially_refunded' => 'This order has been partially refunded.',
            'refunded' => 'This order has been refunded.',
            'product_line_gates' => [
                'paid' => 'پرداخت‌شده: این سفارش قابل تغییر نیست چون پرداخت شده است.',
                'erp' => 'ERP: این سفارش قابل تغییر نیست چون به ERP ارسال شده است.',
                'invoice' => 'فاکتور: این سفارش قابل تغییر نیست چون فاکتور دارد.',
                'awb' => 'AWB: این سفارش قابل تغییر نیست چون AWB دارد.',
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
            'label' => 'Update Status',
            'notification' => 'Orders status updated',
        ],
        'update_status' => [
            'new_status' => [
                'label' => 'New status',
            ],
            'additional_content' => [
                'label' => 'Additional content',
            ],
            'additional_email_recipient' => [
                'label' => 'Additional email recipient',
                'placeholder' => 'optional',
            ],
        ],
        'download_order_pdf' => [
            'label' => 'Download PDF',
            'notification' => 'Order PDF downloading',
        ],
        'edit_address' => [
            'label' => 'Edit',

            'notification' => [
                'error' => 'Error',

                'billing_address' => [
                    'saved' => 'Billing address saved',
                ],

                'shipping_address' => [
                    'saved' => 'Shipping address saved',
                ],
            ],
        ],
        'edit_tags' => [
            'label' => 'Edit',
            'form' => [
                'tags' => [
                    'label' => 'Tags',
                    'helper_text' => 'Separate tags by pressing Enter, Tab or comma (,)',
                ],
            ],
        ],
        'capture_payment' => [
            'label' => 'Capture Payment',

            'notification' => [
                'error' => 'There was a problem with the capture',
                'success' => 'Capture successful',
            ],
        ],
        'refund_payment' => [
            'label' => 'Refund',

            'notification' => [
                'error' => 'There was a problem with the refund',
                'success' => 'Refund successful',
            ],
        ],
        'add_product' => [
            'label' => 'افزودن محصول',
            'product' => [
                'label' => 'محصول',
            ],
            'variant' => [
                'label' => 'گونه',
            ],
            'quantity' => [
                'label' => 'تعداد',
            ],
            'notification' => 'محصول اضافه شد',
        ],
        'update_quantity' => [
            'label' => 'به‌روزرسانی تعداد',
            'quantity' => [
                'label' => 'تعداد',
            ],
            'notification' => 'تعداد به‌روز شد',
        ],
        'remove_product' => [
            'label' => 'حذف محصول',
            'confirmation' => 'این محصول از سفارش حذف شود؟',
            'notification' => 'محصول حذف شد',
        ],
        'product_line_edit' => [
            'last_line' => 'سفارش باید حداقل یک محصول را نگه دارد.',
            'unauthorized' => 'نمی‌توانید محصولات این سفارش را تغییر دهید.',
            'cart' => 'این سفارش سبد ندارد، بنابراین محصولات آن قابل تغییر نیستند.',
            'line' => 'این ردیف محصول پیدا نشد.',
            'purchasable' => 'این گونه پیدا نشد.',
            'shipping' => 'گزینه ارسال قابل قیمت‌گذاری نیست، بنابراین سفارش بدون تغییر ماند.',
            'locked' => 'این سفارش دیگر قابل ویرایش نیست.',
            'not_purchasable' => 'این گونه قابل خرید نیست.',
            'out_of_stock' => 'این گونه موجود نیست.',
            'insufficient_stock' => 'موجودی برای این تعداد کافی نیست.',
            'missing_price' => 'این گونه در واحد پول سفارش قیمت ندارد.',
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
