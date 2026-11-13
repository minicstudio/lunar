<?php

namespace Lunar\Addons\Shipping\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Component;
use Lunar\Addons\Shipping\Services\ShippingService;
use Lunar\Admin\Support\Livewire\FileDownload;
use Lunar\Models\Order;

class DownloadAwbPdfAction extends Action
{
    /**
     * Create a new action instance.
     */
    public static function make(?string $name = 'download_awb_pdf'): static
    {
        return parent::make($name)
            ->label(__('Download AWB PDF'))
            ->action(fn ($livewire) => static::download($livewire->record, $livewire));
    }

    /**
     * Fetch the AWB PDF and queue it for the browser.
     */
    public static function download(Order $order, Component $component): void
    {
        $awbNumber = $order->meta['awb'] ?? null;

        if (! $awbNumber) {
            Notification::make()
                ->title(__('Failed to download PDF.'))
                ->danger()
                ->send();

            return;
        }

        $response = app(ShippingService::class)->downloadAWBPDF($order);

        if (! $response) {
            return;
        }

        if ($response->successful()) {
            FileDownload::queue($component, $response->body(), "AWB-{$awbNumber}.pdf");

            return;
        }

        Notification::make()
            ->title(__('Failed to download PDF.'))
            ->danger()
            ->send();
    }
}
