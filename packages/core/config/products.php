<?php

use Lunar\Base\Enums\ProductAssociation;

return [
    'association_types_enum' => ProductAssociation::class,

    /*---------------------------------------------------------------------------
    | Product Condition
    |---------------------------------------------------------------------------
    | The default condition for products.
    | Possible values: new, refurbished, used
    |*/
    'product_condition' => env('PRODUCT_CONDITION', 'new'),
];
