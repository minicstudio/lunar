<?php

namespace Lunar\Base;

use Illuminate\Database\Eloquent\Builder;

interface HasCustomerGroupAvailability
{
    /**
     * Scope to only include customer groups that are available to the user.
     *
     * This method should filter customer groups based on visibility, availability, and optional purchasability.
     * It considers customer group constraints like `starts_at` and `ends_at` to determine if the
     * group is currently available.
     *
     * @param  Builder  $query
     */
    public function scopeAvailableCustomerGroups($query): Builder;
}
