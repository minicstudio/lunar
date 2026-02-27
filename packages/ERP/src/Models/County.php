<?php

namespace Lunar\ERP\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Base\BaseModel;
use Lunar\Models\Country;

class County extends BaseModel implements Contracts\County
{
    /**
     * The table associated with the model.
     */
    protected $table = 'counties';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code',
        'country_id',
    ];

    /**
     * {@inheritDoc}
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * {@inheritDoc}
     */
    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class);
    }
}
