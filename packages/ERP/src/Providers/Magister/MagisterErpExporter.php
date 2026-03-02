<?php

namespace Lunar\ERP\Providers\Magister;

use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Contracts\ErpDataExporterInterface;
use Lunar\Models\Order;
use Saloon\Http\Response;

class MagisterErpExporter implements ErpDataExporterInterface
{
    /**
     * The ERP API client instance.
     */
    protected ErpApiClientInterface $client;

    /**
     * Create a new Magister ERP exporter instance.
     */
    public function __construct(ErpApiClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Send order to Magister ERP system.
     */
    public function sendOrder(Order $order): bool
    {
        $response = $this->client->sendOrder($order);

        // Assuming the API client returns a success indicator
        return isset($response['success']) && $response['success'] === true;
    }

    /**
     * {@inheritDoc}
     */
    public function generateInvoice(Order $order): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function downloadInvoicePDF(Order $order): ?Response
    {
        return null;
    }
}
