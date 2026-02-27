<?php

namespace Lunar\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Base\BaseModel;

class Locality extends BaseModel implements Contracts\Locality
{
    /**
     * The table associated with the model.
     */
    protected $table = 'localities';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'postal_code',
        'county_id',
    ];

    /**
     * {@inheritDoc}
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }
}
