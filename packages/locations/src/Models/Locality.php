<?php

namespace Lunar\Locations\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lunar\Base\BaseModel;

/**
 * @property int $id
 * @property string $name
 * @property string $postal_code
 * @property int $county_id
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
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
     *
     * @return BelongsTo<County, $this>
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }
}
