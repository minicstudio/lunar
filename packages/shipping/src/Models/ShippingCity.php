<?php

namespace Lunar\Addons\Shipping\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Lunar\Base\BaseModel;

class ShippingCity extends BaseModel implements Contracts\ShippingCity
{
    use SoftDeletes;

    protected $fillable = [
        'provider',
        'provider_city_id',
        'name',
        'postal_code',
        'county_id',
        'provider_county_id',
    ];
}
