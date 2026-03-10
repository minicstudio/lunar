<?php

namespace Lunar\Addons\Shipping\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Lunar\Base\BaseModel;

class ShippingLocker extends BaseModel implements Contracts\ShippingLocker
{
    use SoftDeletes;

    protected $fillable = [
        'provider',
        'provider_locker_id',
        'name',
        'locker_type',
        'county',
        'county_id',
        'provider_county_id',
        'city',
        'city_id',
        'provider_city_id',
        'postal_code',
        'address',
        'lat',
        'lng',
    ];
}
