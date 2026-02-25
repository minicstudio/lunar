<?php

namespace Lunar\Addons\Shipping\Providers\Sameday\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class AuthenticateRequest extends Request
{
    /**
     * The HTTP method to use for the request.
     */
    protected Method $method = Method::POST;

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return '/api/authenticate';
    }

    /**
     * The headers to send with the request.
     */
    public function defaultHeaders(): array
    {
        return [
            'X-AUTH-USERNAME' => config('lunar.shipping.sameday.username'),
            'X-AUTH-PASSWORD' => config('lunar.shipping.sameday.password'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    public function defaultBody(): array
    {
        return [
            'remember_me' => 'true',
        ];
    }
}
