<?php

namespace Lunar\Addons\Shipping\Providers\Sameday\Requests;

use Lunar\Addons\Shipping\Providers\Sameday\SamedayTokenProvider;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCounties extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/api/geolocation/county';
    }

    /**
     * The request headers.
     */
    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-AUTH-TOKEN' => app(SamedayTokenProvider::class)->getToken(),
        ];
    }
}
