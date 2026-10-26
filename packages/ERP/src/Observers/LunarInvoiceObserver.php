<?php

namespace Lunar\ERP\Observers;

use Lunar\ERP\Services\InvoiceDocumentService;
use Lunar\Models\Order;

class LunarInvoiceObserver
{
    /**
     * Handle the order "updated" event for Lunar DomPDF invoicing.
     */
    public function updated(Order $order): void
    {
        $invoiceDocuments = app(InvoiceDocumentService::class);

        if (
            $invoiceDocuments->isErpBillingActive()
            || ! $order->wasChanged('status')
            || filled($order->meta['lunar_invoice_generated_at'] ?? null)
            || ! $invoiceDocuments->isLunarInvoiceEligible($order)
        ) {
            return;
        }

        $invoiceDocuments->markLunarInvoiceGenerated($order);
        $invoiceDocuments->dispatchInvoiceGeneratedUnlessEmailed($order, 'lunar');
    }
}
