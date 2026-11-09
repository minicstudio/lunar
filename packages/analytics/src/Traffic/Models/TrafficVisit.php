<?php

namespace Lunar\Analytics\Traffic\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Lunar\Analytics\Database\Factories\TrafficVisitFactory;
use Lunar\Base\BaseModel;

/**
 * One storefront visit: where the visitor came from and where they landed.
 *
 * @property int $id
 * @property string $source
 * @property ?string $medium
 * @property ?string $campaign
 * @property ?string $referrer_host
 * @property string $landing_path
 * @property ?Carbon $created_at
 */
class TrafficVisit extends BaseModel
{
    use HasFactory;

    /**
     * Visits are never updated.
     */
    public const UPDATED_AT = null;

    /**
     * The table associated with the model (prefixed by BaseModel).
     *
     * @var string
     */
    protected $table = 'traffic_visits';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'medium',
        'campaign',
        'referrer_host',
        'landing_path',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): TrafficVisitFactory
    {
        return TrafficVisitFactory::new();
    }
}
