<?php

namespace Lunar\Addons\Shipping\Providers\Sameday\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GeocodeCountyRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * Create a new request instance.
     *
     * @param  string  $countyName  The county name to geocode.
     * @param  string  $country  The country context for the geocode request.
     */
    public function __construct(
        protected string $countyName,
        protected string $country = 'Romania',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/search';
    }

    /**
     * The query parameters to send with the request.
     */
    public function defaultQuery(): array
    {
        return [
            'format' => 'json',
            'q' => $this->countyName.', '.$this->country,
        ];
    }
}
