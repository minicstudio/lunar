<?php

namespace Lunar\ERP\Providers\Magister;

use Illuminate\Support\Facades\Log;
use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Contracts\ErpProviderInterface;
use Lunar\ERP\Contracts\SupportsLocalities;
use Lunar\ERP\Exceptions\ErpSyncException;

class MagisterErpProvider implements ErpProviderInterface, SupportsLocalities
{
    /**
     * The ERP API client instance.
     */
    protected ErpApiClientInterface $client;

    /**
     * Create a new Magister ERP provider instance.
     */
    public function __construct(ErpApiClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Whether the ERP provider is enabled.
     */
    public function isEnabled(): bool
    {
        return config('lunar.erp.magister.enabled', false);
    }

    /**
     * Get the provider name/identifier.
     */
    public function getProviderName(): string
    {
        return 'magister';
    }

    /**
     * Get the provider-specific data structure for storing in the provider_data JSON column.
     *
     * @param  array  $rawData  The raw data from the ERP system
     * @return array The structured provider-specific data
     */
    public function getProviderSpecificData(array $rawData): array
    {
        return [
            'article_kind' => $rawData['ARTICLE_KIND'] ?? null,
            'generic_article_id' => $rawData['IDSMARTCASH_GENERIC_ARTICLE'] ?? null,
            'recversion' => $rawData['RECVERSION'] ?? null,
        ];
    }

    /**
     * Get localities from Magister ERP system.
     */
    public function getLocalities(): array
    {
        try {
            $response = $this->client->getLocalities();

            if (empty($response) || empty($response['result']) || empty($response['result'][0]['DATASET'])) {
                return [];
            }

            $localities = [];

            $responseData = $response['result'][0]['DATASET'] ?? [];

            // Process the response and format it for our needs
            foreach ($responseData as $item) {
                $localities[] = [
                    'countyCode' => $item['COUNTY_CODE'] ?? null,
                    'countyName' => $item['COUNTY'] ?? null,
                    'localityName' => $item['TOWN'] ?? null,
                ];
            }

            return $localities;
        } catch (\Exception $e) {
            Log::error('Magister ERP localities sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ErpSyncException('Failed to sync localities from Magister ERP: '.$e->getMessage());
        }
    }

    /**
     * Get attributes from Magister ERP system.
     */
    public function getAttributes(): array
    {
        try {
            $response = $this->client->getAttributes();

            if (empty($response) || empty($response['result']) || empty($response['result'][0]['DATASET'])) {
                return [];
            }

            $attributes = [];

            $responseData = $response['result'][0]['DATASET'] ?? [];

            // Process the response and format it for our needs
            foreach ($responseData as $item) {
                $attribute = [
                    'optionName' => $item['NAME'] ?? null,
                    'optionValues' => [],
                ];
                // loop through options
                foreach ($item['ITEMS'] as $option) {
                    $attribute['optionValues'][] = $option['NAME'] ?? null;
                }

                $attributes[] = $attribute;
            }

            return $attributes;
        } catch (\Exception $e) {
            Log::error('Magister ERP attributes sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ErpSyncException('Failed to sync attributes from Magister ERP: '.$e->getMessage());
        }
    }
}
