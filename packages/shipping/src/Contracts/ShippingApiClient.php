<?php

namespace Lunar\Addons\Shipping\Contracts;

use Saloon\Http\Response;

interface ShippingApiClient
{
    /**
     * Generate AWB
     *
     * @throws \Lunar\Addons\Shipping\Exceptions\ShippingInitializationException
     */
    public function generateAWB(?AWBRequestBodyInterface $payload): array;

    /**
     * Download the AWB PDF.
     *
     * @throws \Lunar\Addons\Shipping\Exceptions\FailedAWBGenerationException
     */
    public function downloadAWBPDF(string $awbNumber): ?Response;
}
