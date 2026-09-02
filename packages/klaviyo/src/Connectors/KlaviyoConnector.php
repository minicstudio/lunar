<?php

namespace Lunar\Klaviyo\Connectors;

use Saloon\Http\Auth\HeaderAuthenticator;
use Saloon\Http\Connector;

class KlaviyoConnector extends Connector
{
    public function __construct(
        protected string $apiKey = '',
        protected string $revision = '2026-01-15',
    ) {}

    public function resolveBaseUrl(): string
    {
        return 'https://a.klaviyo.com/api';
    }

    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/vnd.api+json',
            'revision' => $this->revision,
        ];
    }

    protected function defaultAuth(): HeaderAuthenticator
    {
        // Saloon HeaderAuthenticator has no prefix arg — send the full header value.
        return new HeaderAuthenticator('Klaviyo-API-Key '.$this->apiKey, 'Authorization');
    }
}
