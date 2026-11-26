<?php

namespace Lunar\Analytics\Conversions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Lunar\Analytics\Conversions\Enums\ConversionEventType;
use Lunar\Analytics\Database\Factories\ConversionEventFactory;
use Lunar\Base\BaseModel;

/**
 * @property int $id
 * @property ConversionEventType $type
 * @property int $cart_id
 * @property ?Carbon $created_at
 */
class ConversionEvent extends BaseModel
{
    use HasFactory;

    /**
     * Events are never updated.
     */
    public const UPDATED_AT = null;

    /**
     * The table associated with the model (prefixed by BaseModel).
     *
     * @var string
     */
    protected $table = 'conversion_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'cart_id',
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
            'type' => ConversionEventType::class,
            'cart_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Return a new factory instance for the model.
     */
    protected static function newFactory(): ConversionEventFactory
    {
        return ConversionEventFactory::new();
    }
}
