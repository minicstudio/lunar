<?php

namespace Lunar\Addons\Shipping\Providers\Sameday\Requests;

use Lunar\Addons\Shipping\Providers\Sameday\SamedayTokenProvider;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class GenerateAWBRequest extends Request implements HasBody
{
    use HasJsonBody;

    /**
     * The HTTP method to use for the request.
     */
    protected Method $method = Method::POST;

    /**
     * Create a new request instance.
     *
     * @param  array  $payload  The payload to send in the request body.
     */
    public function __construct(
        protected array $payload,
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return '/api/awb';
    }

    /**
     * The request headers.
     */
    public function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-AUTH-TOKEN' => app(SamedayTokenProvider::class)->getToken(),
        ];
    }

    /**
     * The request body.
     */
    public function defaultBody(): array
    {
        return $this->payload;
    }
}
