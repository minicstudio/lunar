<?php

namespace Lunar\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Scope a query to only include legal customer types.
     */
    public function scopeLegal(Builder $query): Builder
    {
        return $query->where('name', 'legal');
    }
}
