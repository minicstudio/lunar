<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Smartbill Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Disable this to turn off only the Smartbill billing provider logic.
    |
    */
    'enabled' => env('SMARTBILL_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Billing Provider Class
    |--------------------------------------------------------------------------
    |
    | The class responsible for implementing the billing provider logic.
    |
    */
    'provider_class' => \Lunar\ERP\Providers\Smartbill\SmartbillErpProvider::class,

    /*
    |--------------------------------------------------------------------------
    | ERP Data Exporter Class
    |--------------------------------------------------------------------------
    |
    | The class responsible for exporting data to the ERP system.
    |
    */
    'exporter_class' => \Lunar\ERP\Providers\Smartbill\SmartbillErpExporter::class,

    /*
    |--------------------------------------------------------------------------
    | API Client Class
    |--------------------------------------------------------------------------
    |
    | The class used to communicate with the external billing provider's API.
    |
    */
    'client_class' => \Lunar\ERP\Providers\Smartbill\SmartbillApiClient::class,

    /*
    |--------------------------------------------------------------------------
    | Smartbill API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Smartbill API endpoints.
    |
    */
    'base_url' => env('SMARTBILL_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Smartbill Email
    |--------------------------------------------------------------------------
    |
    | Your Smartbill account email used for authentication.
    |
    */
    'email' => env('SMARTBILL_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Smartbill Token
    |--------------------------------------------------------------------------
    |
    | Your Smartbill account token used for authentication.
    |
    */
    'token' => env('SMARTBILL_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Company VAT Code
    |--------------------------------------------------------------------------
    |
    | The VAT code (CIF) of your company used on invoices.
    |
    */
    'company_vat_code' => env('SMARTBILL_COMPANY_VAT_CODE'),

    /*
    |--------------------------------------------------------------------------
    | Series Name
    |--------------------------------------------------------------------------
    |
    | The Smartbill invoice series name to use when issuing invoices.
    |
    */
    'series_name' => env('SMARTBILL_SERIES_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Measuring Unit Name
    |--------------------------------------------------------------------------
    |
    | The name of the measuring unit sent to Smartbill for invoice line items
    | (e.g., "pcs", "kg"). Ensures consistency with Smartbill's unit definitions.
    |
    */
    'measuring_unit_name' => env('SMARTBILL_MEASURING_UNIT_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Save To DB
    |--------------------------------------------------------------------------
    |
    | Whether to persist created invoices in Smartbill's database.
    |
    */
    'save_to_db' => env('SMARTBILL_SAVE_TO_DB', false),

    /*
    |--------------------------------------------------------------------------
    | Tax Names by Percentage
    |--------------------------------------------------------------------------
    |
    | The key is the tax percentage (integer or string),
    | The value is the label Smartbill expects.
    |
    */
    'tax_names' => [
        '21' => 'Normala',
        '11' => 'Redusa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Generation Triggers
    |--------------------------------------------------------------------------
    |
    | The order status changes that trigger invoice generation.
    |
    */
    'generate_invoice' => ['awaiting-payment'],
];
