<?php

namespace Lunar\Klaviyo\Connectors;

use Saloon\Http\Auth\HeaderAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class KlaviyoConnector extends Connector
{
    use AcceptsJson;

    public function __construct(
        protected string $apiKey = '',
        protected string $revision = '2024-10-15',
    ) {}

    public function resolveBaseUrl(): string
    {
        return 'https://a.klaviyo.com/api';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'revision' => $this->revision,
        ];
    }

    protected function defaultAuth(): HeaderAuthenticator
    {
        // Saloon HeaderAuthenticator has no prefix arg — send the full header value.
        return new HeaderAuthenticator('Klaviyo-API-Key '.$this->apiKey, 'Authorization');
    }
}
