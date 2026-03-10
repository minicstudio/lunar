<?php

namespace Lunar\Addons\Shipping\Models\Contracts;

interface ShippingProviderCredentials
{
    /**
     * Query for a specific provider.
     */
    public static function for(string $provider): ?self;

    /**
     * Get the token for a specific provider.
     */
    public static function validTokenFor(string $provider): ?string;
}
