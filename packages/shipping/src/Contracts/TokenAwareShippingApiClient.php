<?php

namespace Lunar\Addons\Shipping\Contracts;

interface TokenAwareShippingApiClient extends ShippingApiClient
{
    /**
     * Retrieve an authentication token.
     */
    public function getToken(): string;
}
