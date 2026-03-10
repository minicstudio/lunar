<?php

namespace Lunar\ERP\Providers\Smartbill\Requests;

use Saloon\Contracts\Authenticator;
use Saloon\Enums\Method;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Request;

class DownloadInvoicePDFRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * Create a new request instance.
     */
    public function __construct(
        protected array $payload
    ) {}

    /**
     * The endpoint to send the request to.
     */
    public function resolveEndpoint(): string
    {
        return '/invoice/pdf';
    }

    /**
     * The request headers.
     */
    public function defaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/octet-stream',
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
     * The request query parameters.
     */
    public function defaultQuery(): array
    {
        return $this->payload;
    }
}
