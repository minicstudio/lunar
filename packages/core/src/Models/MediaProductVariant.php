<?php

namespace Lunar\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Assignment of a product media to a variant (admin: Product variant → Media).
 *
 * Used as the pivot of ProductVariant::images() so attaching, detaching and
 * toggling the primary flag fire model events.
 *
 * @property int $media_id
 * @property int $product_variant_id
 * @property bool $primary
 * @property int $position
 */
class MediaProductVariant extends Pivot
{
    /**
     * {@inheritDoc}
     */
    protected $casts = [
        'primary' => 'boolean',
    ];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('lunar.database.table_prefix').'media_product_variant';
    }
}
