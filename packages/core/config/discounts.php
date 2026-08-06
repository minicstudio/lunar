<?php

use Lunar\Base\Validation\CouponValidator;

return [

    /*
    |--------------------------------------------------------------------------
    | Coupon Validator
    |--------------------------------------------------------------------------
    |
    | Here you can specify the class which validates coupons. This is useful if you
    | want to have custom logic for what determines whether a coupon can be used.
    |
    */
    'coupon_validator' => CouponValidator::class,

    /*
    |--------------------------------------------------------------------------
    | Fixed Value Discount Minimum Remaining Percentage
    |--------------------------------------------------------------------------
    |
    | Minimum % of the original price that must remain after a coupon-less
    | fixed value discount, otherwise it's not applied. 0 allows a free product.
    |
    */
    'fixed_value_minimum_remaining_percentage' => 10,

];
