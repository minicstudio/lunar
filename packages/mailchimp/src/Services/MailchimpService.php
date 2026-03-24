<?php

namespace Lunar\Mailchimp\Services;

use Lunar\Mailchimp\Connectors\MailchimpConnector;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Exceptions\MissingMailchimpConfigurationException;
use Lunar\Mailchimp\Requests\CreateStoreRequest;
use Lunar\Mailchimp\Requests\GetStoreRequest;

class MailchimpService
{
    protected MailchimpConnector $connector;

    protected string $listId;

    protected string $storeId;

    /**
     * @throws MissingMailchimpConfigurationException
     */
    public function __construct()
    {
        $apiKey = config('lunar-frontend.mailchimp.api_key');
        $listId = config('lunar-frontend.mailchimp.list_id');
        $storeId = config('lunar-frontend.mailchimp.store_id');
        $server = config('lunar-frontend.mailchimp.server', 'us1');

        if (empty($apiKey) || empty($listId)) {
            throw new MissingMailchimpConfigurationException('Missing Mailchimp configuration. Please set MAILCHIMP_API_KEY and MAILCHIMP_LIST_ID in your environment.');
        }

        $this->listId = $listId;
        $this->storeId = $storeId ?? '';
        $this->connector = new MailchimpConnector($server, $apiKey);
    }

    public function getConnector(): MailchimpConnector
    {
        return $this->connector;
    }

    public function getListId(): string
    {
        return $this->listId;
    }

    public function getStoreId(): string
    {
        return $this->storeId;
    }

    /**
     * Create a new Mailchimp store for Ecommerce API.
     *
     * @throws FailedMailchimpSyncException
     */
    public function createStore(
        string $storeId,
        string $storeName,
        string $domain,
        string $emailAddress,
        string $currencyCode
    ): array {
        $data = [
            'id' => $storeId,
            'list_id' => $this->listId,
            'name' => $storeName,
            'domain' => $domain,
            'email_address' => $emailAddress,
            'currency_code' => $currencyCode,
        ];

        $request = new CreateStoreRequest($data);
        $response = $this->connector->send($request);

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to create store: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Get a Mailchimp store by ID.
     *
     * @throws FailedMailchimpSyncException
     */
    public function getStore(string $storeId): array
    {
        $request = new GetStoreRequest($storeId);
        $response = $this->connector->send($request);

        if (! $response->successful()) {
            throw new FailedMailchimpSyncException("Failed to get store: {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Generate consistent customer ID from email address.
     * Uses MD5 hash to ensure same ID for guest and registered users.
     */
    public function getCustomerIdFromEmail(string $email): string
    {
        return md5(strtolower(trim($email)));
    }

    /**
     * Ensure store ID is configured before calling Ecommerce API methods.
     *
     * @throws MissingMailchimpConfigurationException
     */
    public function ensureStoreIdIsSet(): void
    {
        if (empty($this->storeId)) {
            throw new MissingMailchimpConfigurationException('Store ID is required for Ecommerce API operations. Please set MAILCHIMP_STORE_ID in your environment or create a store using: php artisan mailchimp:create-store');
        }
    }
}
