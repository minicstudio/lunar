<?php

namespace Lunar\ERP\Contracts;

use Lunar\ERP\Exceptions\FailedErpInvoiceGenerationException;
use Lunar\Models\Order;
use Saloon\Http\Response;

interface ErpDataExporterInterface
{
    /**
     * Send order to ERP system
     */
    public function sendOrder(Order $order): bool;

    /**
     * Generate an invoice for the given order.
     *
     * @throws FailedErpInvoiceGenerationException
     */
    public function generateInvoice(Order $order): array;

    /**
     * Download the invoice PDF for the given order.
     */
    public function downloadInvoicePDF(Order $order): ?Response;
}
