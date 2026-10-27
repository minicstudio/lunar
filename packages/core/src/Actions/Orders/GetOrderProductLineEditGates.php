<?php

namespace Lunar\Actions\Orders;

use Lunar\Actions\AbstractAction;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;

final class GetOrderProductLineEditGates extends AbstractAction
{
    /**
     * Statuses that still mean the order has not been accepted by the ERP
     * when `meta.erp_sent_at` is empty.
     *
     * @var list<string>
     */
    private const PRE_SEND_STATUSES = [
        'awaiting-payment',
        'failed-erp-sync',
        'invalid-address',
    ];

    /**
     * Failed gates that block a product-line edit, in display order.
     *
     * @return list<string>
     */
    public function execute(OrderContract $order): array
    {
        /** @var Order $order */
        $failed = [];

        if ($this->orderIsPaid($order)) {
            $failed[] = 'paid';
        }

        if ($this->orderWasSentToErp($order)) {
            $failed[] = 'erp';
        }

        if ($this->orderHasInvoice($order)) {
            $failed[] = 'invoice';
        }

        if ($this->orderHasAwb($order)) {
            $failed[] = 'awb';
        }

        return $failed;
    }

    private function orderIsPaid(Order $order): bool
    {
        if ($order->status === 'payment-received') {
            return true;
        }

        return $order->transactions()
            ->where('type', 'capture')
            ->where('success', true)
            ->exists();
    }

    private function orderWasSentToErp(Order $order): bool
    {
        if (filled($this->metaValue($order, 'erp_sent_at'))) {
            return true;
        }

        if (! $this->orderSendingIsEnabled()) {
            return false;
        }

        return ! in_array($order->status, self::PRE_SEND_STATUSES, true);
    }

    private function orderHasInvoice(Order $order): bool
    {
        return filled($this->metaValue($order, 'billing_series'))
            && filled($this->metaValue($order, 'billing_number'));
    }

    private function orderHasAwb(Order $order): bool
    {
        return filled($this->metaValue($order, 'awb'));
    }

    /**
     * Whether an enabled provider is allowed to send orders.
     *
     * Mirrors ErpService::getAllowedProviders('actions', 'send_order') without depending on the ERP package.
     */
    private function orderSendingIsEnabled(): bool
    {
        if (! config('lunar.erp.enabled')) {
            return false;
        }

        $allowed = array_map('strval', (array) config('lunar.erp.actions.send_order', []));

        foreach ((array) config('lunar.erp.providers', []) as $provider) {
            $provider = (string) $provider;

            if (! in_array($provider, $allowed, true)) {
                continue;
            }

            if (config("lunar.erp.{$provider}.enabled", false)) {
                return true;
            }
        }

        return false;
    }

    private function metaValue(Order $order, string $key): mixed
    {
        if ($order->meta === null || ! isset($order->meta[$key])) {
            return null;
        }

        return $order->meta[$key];
    }
}
