<?php

return [

    'label' => 'Order',

    'plural_label' => 'Orders',

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
        'meta' => [
            'bt_order_number' => 'BT order number',
            'register_error_code' => 'Registration error code',
            'installments' => 'Instalments',
            'payment_way' => 'Payment way',
            'action_code' => 'Action code (bank response to the card operation)',
            'approved_amount' => 'Approved amount',
            'loyalty_amount' => 'Paid in StarBT points',
            'loyalty_action_code' => 'StarBT points action code',
            'reverse_requested_at' => 'Hold release requested at',
            'reverse_trigger' => 'Hold release reason',
        ],
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
            'no_options_message' => 'No tags found',
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
        'show_draft_orders' => [
            'label' => 'Show orders not yet placed',
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
        'discount_breakdown' => [
            'coupon' => 'Coupon',
            'coupon_with_name' => 'Coupon (:name)',
            'discount' => 'Discount',
            'named' => 'Discount :name',
        ],
        'total' => [
            'label' => 'Total',
        ],
        'preorder' => [
            'message' => 'This product is only available for preorder.',
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
            'label' => 'Shipping Details',
        ],
        'billing_details' => [
            'label' => 'Billing Details',
        ],
        'personalization' => [
            'label' => 'Personalization',
            'sku' => 'SKU',
            'value' => 'Personalization text',
        ],
        'meta' => [
            'is_guest' => 'Guest checkout',
            'language_locale' => 'Language',
            'shippingType' => 'Shipping type',
            'shipping_zone' => 'Shipping zone',
            'awb' => 'AWB number',
            'payment_option' => 'Payment method',
            'payment_type' => 'Payment type',
            'isBillingSameAsShipping' => 'Billing address same as shipping',
            'billing_series' => 'Invoice series',
            'billing_number' => 'Invoice number',
        ],
        'shipping_types' => [
            'courier' => 'Courier',
            'locker' => 'Parcel locker',
            'pickup' => 'Personal pickup',
        ],
        'payment_methods' => [
            'offline' => 'Offline',
            'cash-in-hand' => 'Cash in hand',
            'cash-on-delivery' => 'Cash on delivery',
            'bank-transfer' => 'Bank transfer',
            'stripe-card' => 'Credit card',
            'stripe-wallet-pay' => 'Apple Pay / Google Pay',
            'hosted-payment' => 'Online card payment',
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
            'draft_order' => 'This order has not been placed yet.',
            'requires_capture' => 'This order still requires payment to be captured.',
            'partially_refunded' => 'This order has been partially refunded.',
            'refunded' => 'This order has been refunded.',
        ],
    ],

    'action' => [
        'bulk_update_status' => [
            'label' => 'Update Status',
            'notification' => 'Orders status updated',
        ],
        'update_status' => [
            'label' => 'Update Status',
            'notification' => 'Order status updated',
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
            'mailers' => [
                'label' => 'Mailers',
            ],
            'email_addresses' => [
                'label' => 'Email addresses',
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
    ],

];
