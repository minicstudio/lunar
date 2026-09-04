<?php

namespace Lunar\Locations\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lunar\Base\BaseModel;
use Lunar\Models\Country;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $country_id
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
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
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * {@inheritDoc}
     *
     * @return HasMany<Locality>
     */
    public function localities(): HasMany
    {
        return $this->hasMany(Locality::class);
    }
}
