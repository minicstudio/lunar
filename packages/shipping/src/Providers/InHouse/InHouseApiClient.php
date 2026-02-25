<?php

namespace Lunar\Addons\Shipping\Providers\InHouse;

use Illuminate\Support\Str;
use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;
use Lunar\Addons\Shipping\Contracts\ShippingApiClient;
use Saloon\Http\Response;

class InHouseApiClient implements ShippingApiClient
{
    protected string $provider = 'inhouse';

    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return $this->provider;
    }

    /**
     * Generate a random AWB number.
     */
    public function generateAWB(?AWBRequestBodyInterface $payload): array
    {
        return [
            // Generate a unique AWB number with 10 random characters
            'awbNumber' => Str::substr(Str::ulid(), -10),
        ];
    }

    /**
     * Download the AWB PDF.
     * In-house shipping does not have AWB PDFs.
     */
    public function downloadAWBPDF(string $awbNumber): ?Response
    {
        return null;
    }
}
