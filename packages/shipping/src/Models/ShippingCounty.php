<?php

namespace Lunar\Addons\Shipping\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Lunar\Base\BaseModel;

class ShippingCounty extends BaseModel implements Contracts\ShippingCounty
{
    use SoftDeletes;

    protected $fillable = [
        'provider',
        'provider_county_id',
        'name',
        'code',
    ];
}
