<?php

namespace Lunar\ERP\Contracts;

use Lunar\ERP\Exceptions\ErpInitializationException;
use Lunar\Models\Order;
use Saloon\Http\Response;

interface ErpApiClientInterface
{
    /**
     * Get stock levels from ERP system
     */
    public function getStock(array $productCodes = []): array;

    /**
     * Get article stock information from ERP system
     */
    public function getArticleStockByShop(int $articleId): array;

    /**
     * Send order to ERP system
     */
    public function sendOrder(Order $order): array;

    /**
     * Get next modified articles
     */
    public function getProductList(): array;

    /**
     * Confirm receiving data
     *
     * @param  int  $typeOf  The type of data (101 for articles/products)
     * @param  int  $recVersion  The RECVERSION value
     */
    public function confirmReceivingData(int $typeOf, int $recVersion): array;

    /**
     * Get modified orders to sync status
     */
    public function getModifiedOrders(): array;

    /**
     * Generate Invoice
     *
     *
     * @throws ErpInitializationException
     */
    public function generateInvoice(DtoInterface $payload): array;

    /**
     * Download the invoice PDF for the given order.
     */
    public function downloadInvoicePDF(DtoInterface $payload): ?Response;
}
