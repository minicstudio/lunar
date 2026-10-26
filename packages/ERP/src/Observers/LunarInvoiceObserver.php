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
        $paymentOption = $order->meta['payment_option'] ?? null;
        $expectedStatus = is_string($paymentOption)
            ? ((array) config('lunar.invoicing.statuses_by_payment_option', []))[$paymentOption] ?? null
            : null;

        if (
            $invoiceDocuments->isErpBillingActive()
            || ! $order->wasChanged('status')
            || filled($order->meta['lunar_invoice_generated_at'] ?? null)
            || (
                $order->status !== 'completed'
                && (blank($expectedStatus) || $order->status !== $expectedStatus)
            )
        ) {
            return;
        }

        $invoiceDocuments->markLunarInvoiceGenerated($order);
        $invoiceDocuments->dispatchInvoiceGeneratedIfNeeded($order, 'lunar');
    }
}
