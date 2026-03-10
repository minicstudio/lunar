<?php

namespace Lunar\Locations\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

interface County
{
    /**
     * Get the country that this county belongs to.
     */
    public function country(): BelongsTo;

    /**
     * Get the localities that belong to this county.
     */
    public function localities(): HasMany;
}
