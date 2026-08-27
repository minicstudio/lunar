<?php

namespace Lunar\Klaviyo\Services;

use Lunar\Klaviyo\Connectors\KlaviyoConnector;
use Lunar\Klaviyo\Exceptions\MissingKlaviyoConfigurationException;

class KlaviyoService
{
    protected KlaviyoConnector $connector;

    public function __construct()
    {
        $apiKey = $this->normalizeApiKey((string) config('lunar.klaviyo.api_key'));

        if ($apiKey === '') {
            throw new MissingKlaviyoConfigurationException(
                'Missing Klaviyo configuration. Please set KLAVIYO_API_KEY in your environment (private key only, e.g. pk_…).'
            );
        }

        $this->connector = new KlaviyoConnector(
            apiKey: $apiKey,
            revision: (string) config('lunar.klaviyo.api_revision', '2026-01-15'),
        );
    }

    public function getConnector(): KlaviyoConnector
    {
        return $this->connector;
    }

    /**
     * Double opt-in list for ExplicitOptIn.
     */
    public function getListId(): ?string
    {
        $listId = config('lunar.klaviyo.list_id');

        return $listId ? (string) $listId : null;
    }

    /**
     * Single opt-in list for CustomerRegistration / automatic order subscribe.
     */
    public function getAutomaticListId(): ?string
    {
        $listId = config('lunar.klaviyo.automatic_list_id');

        return $listId ? (string) $listId : null;
    }

    /**
     * Accept raw pk_ keys; strip a pasted "Klaviyo-API-Key " prefix if present.
     */
    protected function normalizeApiKey(string $apiKey): string
    {
        $apiKey = trim($apiKey);

        if (str_starts_with($apiKey, 'Klaviyo-API-Key ')) {
            $apiKey = trim(substr($apiKey, strlen('Klaviyo-API-Key ')));
        }

        return $apiKey;
    }
}
