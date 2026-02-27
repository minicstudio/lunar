<?php

namespace Lunar\ERP\Observers;

use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;
use Lunar\Models\Order;

class OrderObserver
{
    /**
     * Handle the "updating" event for the order.
     *
     * @return void
     */
    public function updating(Order $order)
    {
        $this->handleBilling($order);
    }

    /**
     * Handle the billing logic when the order is updated.
     */
    private function handleBilling(Order $order): void
    {
        $erpService = app(ErpService::class);
        $enabledProviders = $erpService->getAllowedProviders('actions', 'billing');

        if (empty($enabledProviders)) {
            return;
        }

        foreach ($enabledProviders as $provider) {
            $this->generateInvoice($provider, $erpService, $order);
        }
    }

    /**
     * Generate an invoice for the order.
     */
    private function generateInvoice(ErpProviderEnum $provider, ErpService $erpService, Order $order): void
    {
        if (! config("lunar.erp.{$provider->value}.enabled")) {
            return;
        }

        if (isset($order->meta['billing_series'], $order->meta['billing_number'])) {
            return;
        }

        if (! $order->isDirty('status')) {
            return;
        }

        $statuses = config("lunar.erp.{$provider->value}.generate_invoice", []);

        if (! in_array($order->status, $statuses, true)) {
            return;
        }

        $erpService->generateInvoice($provider, $order);

        if (! $order->reference) {
            Notification::make()
                ->title(__('Invoice generated successfully for order via :provider.', [
                    'provider' => Str::headline($provider->value),
                ]))
                ->success()
                ->id('invoice-generated')
                ->send();
        }
    }
}
