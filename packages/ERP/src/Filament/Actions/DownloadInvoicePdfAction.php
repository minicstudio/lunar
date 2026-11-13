<?php

namespace Lunar\ERP\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Component;
use Lunar\Admin\Support\Livewire\FileDownload;
use Lunar\ERP\Services\ErpService;
use Lunar\Models\Order;

class DownloadInvoicePdfAction extends Action
{
    /**
     * Create a new action instance.
     */
    public static function make(?string $name = 'download_invoice_pdf'): static
    {
        return parent::make($name)
            ->label(__('Download Invoice PDF'))
            ->action(fn ($livewire) => static::download($livewire->record, $livewire));
    }

    /**
     * Fetch the invoice PDF and queue it for the browser.
     */
    public static function download(Order $order, Component $component): void
    {
        $series = $order->meta['billing_series'] ?? null;
        $number = $order->meta['billing_number'] ?? null;

        if (! $number || ! $series) {
            Notification::make()
                ->title(__('Failed to download PDF.'))
                ->danger()
                ->send();

            return;
        }

        $erpService = app(ErpService::class);
        $enabledProviders = $erpService->getAllowedProviders('actions', 'billing');

        if (empty($enabledProviders)) {
            $enabledProviders = $erpService->getEnabledProviders();
        }

        if (empty($enabledProviders)) {
            return;
        }

        foreach ($enabledProviders as $provider) {
            $response = $erpService->downloadInvoicePDF($provider, $order);

            if (! $response) {
                continue;
            }

            if ($response->successful()) {
                FileDownload::queue($component, $response->body(), "Invoice-{$provider->value}-{$number}.pdf");

                return;
            }
        }

        Notification::make()
            ->title(__('Failed to download PDF.'))
            ->danger()
            ->send();
    }
}
