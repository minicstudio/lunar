<?php

namespace Lunar\Addons\Shipping\Providers\Sameday\Requests;

use Lunar\Addons\Shipping\Providers\Sameday\SamedayTokenProvider;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetLockerLocations extends Request
{
    protected Method $method = Method::GET;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected array $queryParams,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/client/ooh-locations';
    }

    /**
     * The query parameters to send with the request.
     */
    protected function defaultQuery(): array
    {
        return $this->queryParams;
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
