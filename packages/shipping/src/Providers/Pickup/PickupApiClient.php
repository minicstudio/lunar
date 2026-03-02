<?php

namespace Lunar\Addons\Shipping\Providers\Pickup;

use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;
use Lunar\Addons\Shipping\Contracts\ShippingApiClient;
use Saloon\Http\Response;

class PickupApiClient implements ShippingApiClient
{
    protected string $provider = 'pickup';

    /**
     * {@inheritDoc}
     */
    public function getProviderName(): string
    {
        return $this->provider;
    }

    /**
     * Generate an AWB using the API.
     * Personal pickup does not generate AWB, so this is a no-op.
     */
    public function generateAWB(?AWBRequestBodyInterface $payload): array
    {
        return [
            'awbNumber' => null,
        ];
    }

    /**
     * Download the AWB PDF.
     * Personal pickup does not have AWB PDFs.
     */
    public function downloadAWBPDF(string $awbNumber): ?Response
    {
        return null;
    }
}
