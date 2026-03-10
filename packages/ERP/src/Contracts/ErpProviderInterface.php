<?php

namespace Lunar\ERP\Contracts;

interface ErpProviderInterface
{
    /**
     * Whether the ERP provider is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the provider-specific data structure for storing in the provider_data JSON column.
     *
     * @param  array  $rawData  The raw data from the ERP system
     * @return array The structured provider-specific data
     */
    public function getProviderSpecificData(array $rawData): array;

    /**
     * Get the provider name/identifier.
     */
    public function getProviderName(): string;

    /**
     * Get attributes from the ERP system.
     */
    public function getAttributes(): array;
}
