<?php

namespace Lunar\Addons\Shipping\Providers\Sameday;

use Lunar\Addons\Shipping\Models\ShippingProviderCredentials;

class SamedayTokenProvider
{
    /**
     * Create a new SamedayTokenProvider instance.
     */
    public function __construct(
        protected SamedayApiClient $client
    ) {}

    /**
     * Get a valid token for Sameday API requests.
     *
     * This method first checks if there's a valid token stored in the database.
     * If a valid token exists, it returns that token. If not, it uses the
     * SamedayApiClient to refresh the token and returns the new token.
     *
     * @return string The valid token to be used in API requests.
     */
    public function getToken(): string
    {
        $token = ShippingProviderCredentials::validTokenFor('sameday');

        if ($token) {
            return $token;
        }

        return $this->client->refreshToken();
    }

    /**
     * Invalidate the current token to force refresh on next request.
     */
    public function invalidateToken(): void
    {
        ShippingProviderCredentials::where('provider', 'sameday')->delete();
    }
}
