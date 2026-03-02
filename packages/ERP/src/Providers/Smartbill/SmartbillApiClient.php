<?php

namespace Lunar\ERP\Providers\Smartbill;

use Lunar\ERP\Contracts\DtoInterface;
use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Exceptions\FailedErpInvoiceGenerationException;
use Lunar\ERP\Providers\Smartbill\Requests\DownloadInvoicePDFRequest;
use Lunar\ERP\Providers\Smartbill\Requests\GenerateInvoiceRequest;
use Lunar\Models\Order;
use Saloon\Http\Connector;
use Saloon\Http\Response;

class SmartbillApiClient extends Connector implements ErpApiClientInterface
{
    protected string $provider = 'smartbill';

    /**
     * {@inheritDoc}
     */
    public function resolveBaseUrl(): string
    {
        return config('lunar.erp.smartbill.base_url');
    }

    /**
     * {@inheritDoc}
     */
    public function generateInvoice(DtoInterface $payload): array
    {
        $request = new GenerateInvoiceRequest($payload->toArray());

        $response = $this->send($request);

        if (! $response->successful()) {
            throw new FailedErpInvoiceGenerationException('Failed to generate invoice: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * {@inheritDoc}
     */
    public function downloadInvoicePDF(DtoInterface $payload): ?Response
    {
        $request = new DownloadInvoicePDFRequest($payload->toArray());

        $response = $this->send($request);

        if (! $response->successful()) {
            throw new FailedErpInvoiceGenerationException('Failed to download invoice PDF: ' . $response->body());
        }

        return $response;
    }

    /**
     * {@inheritDoc}
     */
    public function getStock(array $productCodes = []): array
    {
        return [];
    }

    /**
     * Get article stock information from ERP system
     */
    public function getArticleStockByShop(int $articleId): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function sendOrder(Order $order): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getProductList(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function confirmReceivingData(int $typeOf, int $recVersion): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getModifiedOrders(): array
    {
        return [];
    }
}
