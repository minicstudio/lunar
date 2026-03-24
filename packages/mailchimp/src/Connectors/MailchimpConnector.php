<?php

namespace Lunar\Mailchimp\Connectors;

use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class MailchimpConnector extends Connector
{
    use AcceptsJson;

    /**
     * Create a new connector instance.
     */
    public function __construct(
        protected string $server = 'us1',
        protected string $apiKey = ''
    ) {}

    /**
     * The base URL for the Mailchimp API.
     */
    public function resolveBaseUrl(): string
    {
        return "https://{$this->server}.api.mailchimp.com/3.0/";
    }

    /**
     * Default headers for every request.
     */
    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Default authentication for every request.
     */
    protected function defaultAuth(): BasicAuthenticator
    {
        return new BasicAuthenticator('anystring', $this->apiKey);
    }
}
