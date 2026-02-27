<?php

namespace Lunar\ERP\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

interface Locality 
{
    /**
     * Get the county that this locality belongs to.
     */
    public function county(): BelongsTo;
}
