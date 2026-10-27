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
     * Whether any ERP provider is configured for billing.
     */
    public function isErpBillingActive(): bool
    {
        return $this->erpService->getAllowedProviders('actions', 'billing') !== [];
    }

    /**
     * Whether a Lunar DomPDF invoice may be issued for the order's current status.
     */
    public function isLunarInvoiceEligible(Order $order): bool
    {
        if ($order->status === 'completed') {
            return true;
        }

        $paymentOption = $order->meta['payment_option'] ?? null;

        if (! is_string($paymentOption)) {
            return false;
        }

        $expectedStatus = ((array) config('lunar.invoicing.statuses_by_payment_option', []))[$paymentOption] ?? null;

        return filled($expectedStatus) && $order->status === $expectedStatus;
    }

    /**
     * Whether an invoice PDF is available for the order (availability, not authorization).
     */
    public function hasCustomerInvoice(Order $order): bool
    {
        if ($this->hasErpBillingMeta($order)) {
            return true;
        }

        if ($this->isErpBillingActive()) {
            return false;
        }

        return $this->isLunarInvoiceEligible($order);
    }

    /**
     * Whether the customer invoice email has already been sent.
     */
    public function hasInvoiceBeenEmailed(Order $order): bool
    {
        return filled($order->meta['invoice_emailed_at'] ?? null);
    }

    /**
     * Resolve the invoice PDF for the order (ERP preferred when billing meta exists).
     */
    public function resolvePdf(Order $order): ?InvoicePdf
    {
        if ($this->hasErpBillingMeta($order)) {
            return $this->resolveErpPdf($order);
        }

        if ($this->isErpBillingActive()) {
            return null;
        }

        return $this->resolveLunarPdf($order);
    }

    /**
     * Mark that the Lunar DomPDF invoice has been generated for the order.
     *
     * Persists via query update so a nested save/refresh does not clear
     * wasChanged() for later Order observers (e.g. loyalty earn on completed).
     */
    public function markLunarInvoiceGenerated(Order $order): void
    {
        $this->persistMetaKey($order, 'lunar_invoice_generated_at', now()->toIso8601String());
    }

    /**
     * Mark that the customer invoice email has been sent.
     */
    public function markInvoiceEmailed(Order $order): void
    {
        $this->persistMetaKey($order, 'invoice_emailed_at', now()->toIso8601String());
    }

    /**
     * Dispatch OrderInvoiceGenerated unless the customer has already been emailed.
     *
     * @param  string  $source  Billing provider value or `lunar`
     */
    public function dispatchInvoiceGeneratedUnlessEmailed(Order $order, string $source): void
    {
        if ($this->hasInvoiceBeenEmailed($order->fresh() ?? $order)) {
            return;
        }

        OrderInvoiceGenerated::dispatch($order, $source);
    }

    /**
     * Persist a meta key without saving the in-memory order instance.
     */
    protected function persistMetaKey(Order $order, string $key, string $value): void
    {
        $meta = (array) $order->meta;
        $meta[$key] = $value;

        Order::withoutEvents(function () use ($order, $meta) {
            Order::query()->whereKey($order->id)->update([
                'meta' => json_encode($meta),
            ]);
        });

        $order->meta = $meta;
        $order->syncOriginalAttribute('meta');
    }

    /**
     * Whether the order has ERP billing series/number meta.
     */
    protected function hasErpBillingMeta(Order $order): bool
    {
        return isset($order->meta['billing_series'], $order->meta['billing_number']);
    }

    /**
     * Download the invoice PDF via configured ERP billing providers.
     */
    protected function resolveErpPdf(Order $order): ?InvoicePdf
    {
        $providers = $this->erpService->getAllowedProviders('actions', 'billing')
            ?: $this->erpService->getEnabledProviders();

        return collect($providers)
            ->map(fn (ErpProviderEnum $provider) => $this->downloadErpPdf($provider, $order))
            ->filter()
            ->first();
    }

    /**
     * Download a PDF from a single ERP provider.
     */
    protected function downloadErpPdf(ErpProviderEnum $provider, Order $order): ?InvoicePdf
    {
        $content = $this->erpService->downloadInvoicePDF($provider, $order)?->body();

        if (blank($content)) {
            return null;
        }

        return new InvoicePdf(
            content: $content,
            filename: "invoice-{$order->meta['billing_series']}-{$order->meta['billing_number']}.pdf",
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
