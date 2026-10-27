<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lunar invoice status by payment option
    |--------------------------------------------------------------------------
    |
    | When SmartBill billing is not active, the Lunar DomPDF invoice is generated
    | (and OrderInvoiceGenerated is dispatched) when the order status changes to
    | the value configured for the order's meta.payment_option. LunarInvoiceObserver
    | also always invoices when the status becomes `completed`.
    |
    */
    'statuses_by_payment_option' => [
        'stripe-card' => env('LUNAR_INVOICE_STATUS_STRIPE_CARD', 'payment-received'),
        'stripe-wallet-pay' => env('LUNAR_INVOICE_STATUS_STRIPE_WALLET_PAY', 'payment-received'),
        'bank-transfer' => env('LUNAR_INVOICE_STATUS_BANK_TRANSFER', 'awaiting-bank-transfer'),
        'cash-on-delivery' => env('LUNAR_INVOICE_STATUS_CASH_ON_DELIVERY', 'payment-offline'),
    ],

];
