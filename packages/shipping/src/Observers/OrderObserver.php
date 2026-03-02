<?php

namespace Lunar\Addons\Shipping\Observers;

use Filament\Notifications\Notification;
use Lunar\Addons\Shipping\Services\ShippingService;
use Lunar\Models\Order;

class OrderObserver
{
    /**
     * Handle the "updated" event for the order.
     *
     * This method handles shipping logic when the order is updated.
     *
     * @return void
     */
    public function updated(Order $order)
    {
        $this->handleShipping($order);
    }

    /**
     * Handle the shipping logic when the order is updated.
     */
    private function handleShipping(Order $order): void
    {
        if (! config('lunar.shipping.enabled')) {
            return;
        }

        $awbGenerationStatus = config('lunar.shipping.generate_awb_on_status');

        if ($order->isDirty('status') && $order->status === $awbGenerationStatus && ! isset($order->meta['awb'])) {
            $this->generateAWBForOrder($order);
        }
    }

    /**
     * Generate an AWB for the order.
     */
    private function generateAWBForOrder(Order $order): void
    {
        // if the order already has an AWB, we do not need to generate a new one
        if (isset($order->meta['awb'])) {
            Notification::make()->title(
                __('AWB already generated for this order.')
            )->warning()->send();

            return;
        }

        app(ShippingService::class)->generateAWB($order);

        if (! isset($order->meta['awb'])) {
            return;
        }

        Notification::make()->title(
            __('AWB generated successfully for order, the PDF file can now be downloaded.')
        )->success()->send();
    }
}
