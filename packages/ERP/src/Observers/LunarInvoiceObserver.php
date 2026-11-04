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
            ! $order->wasChanged('status')
            || $order->awaitsPreorderConsultation()
            || filled($order->meta['lunar_invoice_generated_at'] ?? null)
            || ! $invoiceDocuments->isLunarInvoiceEligible($order)
        ) {
            return;
        }

        // ERP billing owns the statuses in its generate_invoice list. A bank transfer
        // whose status is outside that list still gets a Lunar invoice so the bank
        // transfer email can attach the PDF.
        if ($invoiceDocuments->isErpBillingActive() && ! $invoiceDocuments->bankTransferNeedsLunarInvoice($order)) {
            return;
        }

        $invoiceDocuments->markLunarInvoiceGenerated($order);
        $invoiceDocuments->dispatchInvoiceGeneratedUnlessEmailed($order, 'lunar');
    }
}
