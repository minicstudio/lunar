<?php

namespace Lunar\Addons\Shipping\Models;

use Lunar\Base\BaseModel;

class ShippingProviderCredentials extends BaseModel implements Contracts\ShippingProviderCredentials
{
    protected $fillable = ['provider', 'token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * {@inheritDoc}
     */
    public static function for(string $provider): ?self
    {
        return self::where('provider', $provider)->first();
    }

    /**
     * {@inheritDoc}
     */
    public static function validTokenFor(string $provider): ?string
    {
        $record = self::for($provider);

        return $record && $record->expires_at && $record->expires_at->subHours(3)->greaterThan(now('UTC'))
            ? $record->token
            : null;
    }
}
