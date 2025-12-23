<?php

namespace Lunar\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Lunar\Base\BaseModel;

/**
 * @property int $id
 * @property string $name
 * @property string $label
 */
class AddressCustomerType extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
    ];
}
