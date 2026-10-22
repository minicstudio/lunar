<?php

namespace Lunar\ERP\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Response;
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
            ->action(fn ($livewire) => static::handle($livewire->record));
    }

    /**
     * Handle the action.
     */
    protected static function handle(Order $order)
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

        // Prefer billing providers; fall back to all enabled providers so historical
        // invoices remain downloadable when actions.billing is empty (Lunar DomPDF mode).
        $providers = collect($erpService->getAllowedProviders('actions', 'billing'))
            ->whenEmpty(fn () => collect($erpService->getEnabledProviders()));

        if ($providers->isEmpty()) {
            return;
        }

        $download = $providers
            ->map(function ($provider) use ($erpService, $order, $number) {
                $response = $erpService->downloadInvoicePDF($provider, $order);

                if (! $response?->successful()) {
                    return null;
                }

                return Response::streamDownload(function () use ($response) {
                    echo $response->body();
                }, "Invoice-{$provider->value}-{$number}.pdf", [
                    'Content-Type' => 'application/pdf',
                ]);
            })
            ->filter()
            ->first();

        if ($download !== null) {
            return $download;
        }

        Notification::make()
            ->title(__('Failed to download PDF.'))
            ->danger()
            ->send();
    }
}
