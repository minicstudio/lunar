<?php

namespace Lunar\ERP\Filament\Extensions;

use Lunar\Admin\Support\Extending\ViewPageExtension;
use Lunar\ERP\Filament\Actions\DownloadInvoicePdfAction;
use Lunar\Models\Order;

class ShippingExtension extends ViewPageExtension
{
    /**
     * Add actions to the header of the order view page.
     */
    public function headerActions(array $actions): array
    {
        $order = $this->caller->record;

        if ($this->hasInvoice($order)) {
            $actions[] = DownloadInvoicePdfAction::make();
            
        }

        return $actions;
    }

    /**
     * Check if invoice exists for the order.
     */
    private function hasInvoice(Order $order): bool
    {
        return isset($order->meta['billing_series'], $order->meta['billing_number'])
            && ! empty($order->meta['billing_series'])
            && ! empty($order->meta['billing_number']);
    }
}
