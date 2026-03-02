<?php

namespace Lunar\Addons\Shipping\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Response;
use Lunar\Models\Order;
use Lunar\Addons\Shipping\Services\ShippingService;

class DownloadAwbPdfAction extends Action
{
    /**
     * Create a new action instance.
     */
    public static function make(?string $name = 'download_awb_pdf'): static
    {
        return parent::make($name)
            ->label(__('Download AWB PDF'))
            ->action(fn($livewire) => static::handle($livewire->record));
    }

    /**
     * Handle the action.
     */
    protected static function handle(Order $order)
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
            return Response::streamDownload(function () use ($response) {
                echo $response->body();
            }, "AWB-{$awbNumber}.pdf", [
                'Content-Type' => 'application/pdf',
            ]);
        }

        Notification::make()
            ->title(__('Failed to download PDF.'))
            ->danger()
            ->send();
    }
}
