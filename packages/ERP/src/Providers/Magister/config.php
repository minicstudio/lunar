<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Magister ERP Feature Toggle
    |--------------------------------------------------------------------------
    |
    | Disable this to turn off only the Magister ERP provider logic.
    |
    */
    'enabled' => env('MAGISTER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | ERP Provider Class
    |--------------------------------------------------------------------------
    |
    | The class responsible for implementing the ERP provider logic.
    |
    */
    'provider_class' => \Lunar\ERP\Providers\Magister\MagisterErpProvider::class,

    /*
    |--------------------------------------------------------------------------
    | ERP Data Importer Class
    |--------------------------------------------------------------------------
    |
    | The class responsible for importing data from the ERP system.
    |
    */
    'importer_class' => \Lunar\ERP\Providers\Magister\MagisterErpImporter::class,

    /*
    |--------------------------------------------------------------------------
    | ERP Data Exporter Class
    |--------------------------------------------------------------------------
    |
    | The class responsible for exporting data to the ERP system.
    |
    */
    'exporter_class' => \Lunar\ERP\Providers\Magister\MagisterErpExporter::class,

    /*
    |--------------------------------------------------------------------------
    | API Client Class
    |--------------------------------------------------------------------------
    |
    | The class used to communicate with the external ERP provider's API.
    |
    */
    'client_class' => \Lunar\ERP\Providers\Magister\MagisterApiClient::class,

    /*
    |--------------------------------------------------------------------------
    | Magister API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the Magister API endpoints.
    |
    */
    'base_url' => env('MAGISTER_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Magister App ID
    |--------------------------------------------------------------------------
    |
    */
    'app_id' => env('MAGISTER_APP_ID'),

    /*
    |--------------------------------------------------------------------------
    | Magister Shop ID
    |--------------------------------------------------------------------------
    |
    */
    'shop_id' => env('MAGISTER_SHOP_ID'),
];
