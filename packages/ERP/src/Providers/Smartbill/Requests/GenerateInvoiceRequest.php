<?php

namespace Lunar\ERP\Providers\Smartbill\Requests;

use Saloon\Contracts\Authenticator;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class GenerateInvoiceRequest extends Request implements HasBody
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
        protected array $payload
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return '/invoice';
    }

    /**
     * The request headers.
     */
    public function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function defaultAuth(): ?Authenticator
    {
        return new BasicAuthenticator(
            config('lunar.erp.smartbill.email'),
            config('lunar.erp.smartbill.token')
        );
    }

    /**
     * The request body.
     */
    public function defaultBody(): array
    {
        return $this->payload;
    }
}
