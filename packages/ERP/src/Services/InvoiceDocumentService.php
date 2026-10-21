<?php

namespace Lunar\ERP\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Lunar\ERP\DTOs\InvoicePdf;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Events\OrderInvoiceGenerated;
use Lunar\Models\Order;

class InvoiceDocumentService
{
    /**
     * Create a new invoice document service.
     */
    public function __construct(
        protected ErpService $erpService,
    ) {}

    /**
     * Whether SmartBill is the active billing provider.
     */
    public function isSmartbillBillingActive(): bool
    {
        if (! config('lunar.erp.enabled')) {
            return false;
        }

        if (! config('lunar.erp.smartbill.enabled', false)) {
            return false;
        }

        $billingProviders = (array) config('lunar.erp.actions.billing', []);

        return in_array(ErpProviderEnum::smartbill->value, $billingProviders, true);
    }

    /**
     * Whether the customer can download an invoice PDF for the order.
     */
    public function hasCustomerInvoice(Order $order): bool
    {
        if ($this->hasSmartbillBillingMeta($order)) {
            return true;
        }

        if ($this->isSmartbillBillingActive()) {
            return false;
        }

        return filled($order->meta['lunar_invoice_generated_at'] ?? null);
    }

    /**
     * Whether the customer invoice email has already been sent.
     */
    public function hasInvoiceBeenEmailed(Order $order): bool
    {
        return filled($order->meta['invoice_emailed_at'] ?? null);
    }

    /**
     * Resolve the invoice PDF for the order (SmartBill preferred when meta exists).
     */
    public function resolvePdf(Order $order): ?InvoicePdf
    {
        if ($this->hasSmartbillBillingMeta($order)) {
            return $this->resolveSmartbillPdf($order);
        }

        if ($this->isSmartbillBillingActive()) {
            return null;
        }

        return $this->resolveLunarPdf($order);
    }

    /**
     * Mark that the Lunar DomPDF invoice has been generated for the order.
     */
    public function markLunarInvoiceGenerated(Order $order): void
    {
        Order::withoutEvents(function () use ($order) {
            $meta = (array) $order->meta;
            $meta['lunar_invoice_generated_at'] = now()->toIso8601String();
            $order->meta = $meta;
            $order->save();
        });
    }

    /**
     * Mark that the customer invoice email has been sent.
     */
    public function markInvoiceEmailed(Order $order): void
    {
        Order::withoutEvents(function () use ($order) {
            $meta = (array) $order->meta;
            $meta['invoice_emailed_at'] = now()->toIso8601String();
            $order->meta = $meta;
            $order->save();
        });
    }

    /**
     * Dispatch OrderInvoiceGenerated when the customer has not been emailed yet.
     *
     * @param  'smartbill'|'lunar'  $source
     */
    public function dispatchInvoiceGeneratedIfNeeded(Order $order, string $source): void
    {
        $order->refresh();

        if ($this->hasInvoiceBeenEmailed($order)) {
            return;
        }

        OrderInvoiceGenerated::dispatch($order, $source);
    }

    /**
     * Whether the order has SmartBill billing series/number meta.
     */
    protected function hasSmartbillBillingMeta(Order $order): bool
    {
        return isset($order->meta['billing_series'], $order->meta['billing_number']);
    }

    /**
     * Download the SmartBill invoice PDF for the order.
     */
    protected function resolveSmartbillPdf(Order $order): ?InvoicePdf
    {
        $response = $this->erpService->downloadInvoicePDF(ErpProviderEnum::smartbill, $order);

        if ($response === null) {
            return null;
        }

        $content = $response->body();

        if ($content === '' || $content === null) {
            return null;
        }

        $series = $order->meta['billing_series'];
        $number = $order->meta['billing_number'];

        return new InvoicePdf(
            content: $content,
            filename: "invoice-{$series}-{$number}.pdf",
        );
    }

    /**
     * Generate a Lunar DomPDF order invoice.
     */
    protected function resolveLunarPdf(Order $order): ?InvoicePdf
    {
        if (! class_exists(\Barryvdh\DomPDF\PDF::class)) {
            return null;
        }

        $content = Pdf::loadView('lunarpanel::pdf.order', [
            'record' => $order,
        ])->output();

        $reference = $order->reference ?? $order->id;

        return new InvoicePdf(
            content: $content,
            filename: "invoice-{$reference}.pdf",
        );
    }
}
