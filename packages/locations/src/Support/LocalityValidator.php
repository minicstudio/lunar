<?php

namespace Lunar\Locations\Support;

use Illuminate\Support\Facades\Schema;
use Lunar\Locations\Models\County;
use Lunar\Locations\Models\Locality;

class LocalityValidator
{
    /**
     * Whether locality data is seeded for this shop.
     */
    public static function isAvailable(): bool
    {
        if (! Schema::hasTable((new County)->getTable()) || ! Schema::hasTable((new Locality)->getTable())) {
            return false;
        }

        return County::count() > 0 && Locality::count() > 0;
    }

    /**
     * Whether the given city matches a known locality within the given county.
     */
    public static function matches(string $city, ?string $county): bool
    {
        if (! $county) {
            return false;
        }

        return Locality::where('name', $city)
            ->whereHas('county', fn ($query) => $query->where('name', $county))
            ->with('county')
            ->get()
            ->contains(fn (Locality $locality) => $locality->name === $city && $locality->county->name === $county);
    }
}
