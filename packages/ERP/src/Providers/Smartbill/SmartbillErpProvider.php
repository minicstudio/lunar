<?php

namespace Lunar\ERP\Providers\Smartbill;

use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Contracts\ErpProviderInterface;

class SmartbillErpProvider implements ErpProviderInterface
{
    /**
     * The ERP API client instance.
     */
    protected ErpApiClientInterface $client;

    /**
     * Create a new Smartbill ERP provider instance.
     */
    public function __construct(ErpApiClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * {@inheritDoc}
     */
    public function isEnabled(): bool
    {
        return config('lunar.erp.smartbill.enabled', false);
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return 'smartbill';
    }

    /**
     * {@inheritDoc}
     */
    public function getProviderSpecificData(array $rawData): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributes(): array
    {
        return [];
    }
}
