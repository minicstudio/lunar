<?php

namespace Lunar\Analytics\Traffic\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Support\Carbon;
use Lunar\Analytics\Database\Factories\TrafficVisitFactory;
use Lunar\Base\BaseModel;

/**
 * One storefront visit: where the visitor came from and where they landed.
 *
 * A visit is stored at the first page view of a session and confirmed once
 * the session shows it belongs to a browser (`TrafficVisitRecorder`). Only
 * confirmed visits are reported; unconfirmed ones are pruned after
 * `UNCONFIRMED_RETENTION_DAYS`.
 *
 * @property int $id
 * @property string $source
 * @property ?string $medium
 * @property ?string $campaign
 * @property ?string $referrer_host
 * @property string $landing_path
 * @property ?Carbon $created_at
 * @property ?Carbon $confirmed_at
 */
class TrafficVisit extends BaseModel
{
    use HasFactory;
    use MassPrunable;

    /**
     * Visits have no `updated_at`; only `confirmed_at` is set later.
     */
    public const UPDATED_AT = null;

    /**
     * Days an unconfirmed visit is kept before `model:prune` deletes it.
     */
    public const UNCONFIRMED_RETENTION_DAYS = 30;

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
        'confirmed_at',
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
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * Unconfirmed visits older than `UNCONFIRMED_RETENTION_DAYS`.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()
            ->whereNull('confirmed_at')
            ->where('created_at', '<', now()->subDays(self::UNCONFIRMED_RETENTION_DAYS));
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): TrafficVisitFactory
    {
        return TrafficVisitFactory::new();
    }
}
