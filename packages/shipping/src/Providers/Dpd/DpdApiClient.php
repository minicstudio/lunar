<?php

namespace Lunar\Addons\Shipping\Providers\Dpd;

use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;
use Lunar\Addons\Shipping\Contracts\ShippingApiClient;
use Lunar\Addons\Shipping\Exceptions\FailedAWBGenerationException;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdParcelRef;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdParcelToPrint;
use Lunar\Addons\Shipping\Providers\Dpd\DTOs\DpdPrintRequestBody;
use Lunar\Addons\Shipping\Providers\Dpd\Requests\DownloadAWBPDF;
use Lunar\Addons\Shipping\Providers\Dpd\Requests\GenerateAWBRequest;
use Saloon\Http\Connector;
use Saloon\Http\Response;

class DpdApiClient extends Connector implements ShippingApiClient
{
    protected string $provider = 'dpd';

    /**
     * The base URL for the DPD server endpoint.
     */
    public function resolveBaseUrl(): string
    {
        return config('lunar.shipping.dpd.base_url');
    }

    /**
     * Generate an AWB using the DPD API.
     */
    public function generateAWB(?AWBRequestBodyInterface $payload): array
    {
        $request = new GenerateAWBRequest($payload->toArray());

        $response = $this->send($request);

        return $response->json();
    }

    /**
     * Download the AWB PDF.
     */
    public function downloadAWBPDF(string $awbNumber): ?Response
    {
        $userName = config('lunar.shipping.dpd.username');
        $password = config('lunar.shipping.dpd.password');
        $paperSize = config('lunar.shipping.dpd.paper_size');

        $payload = new DpdPrintRequestBody(
            userName: $userName,
            password: $password,
            paperSize: $paperSize,
            parcels: [
                new DpdParcelToPrint(
                    parcel: new DpdParcelRef(id: $awbNumber)
                ),
            ]
        );

        $request = new DownloadAWBPDF($payload->toArray());

        $response = $this->send($request);

        if (! $response->successful()) {
            throw new FailedAWBGenerationException('Failed to download AWB PDF: ' . $response->body());
        }

        return $response;
    }
}
