<?php

namespace Lunar\Actions\Orders;

use Lunar\Actions\AbstractAction;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;

final class GetOrderSplitGates extends AbstractAction
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
     * Failed gates block a split. The ERP warning is reported separately and does not.
     */
    public function execute(OrderContract $order): OrderSplitGateResult
    {
        /** @var Order $order */
        $failed = [];

        if (in_array($order->status, ['split', 'dispatched'], true)) {
            $failed[] = 'closed';
        }

        if ($this->orderIsPaid($order)) {
            $failed[] = 'paid';
        }

        if ($this->orderHasInvoice($order)) {
            $failed[] = 'invoice';
        }

        if ($this->orderHasAwb($order)) {
            $failed[] = 'awb';
        }

        if (filled($this->metaValue($order, 'erp_sent_at'))) {
            $failed[] = 'erp';
        }

        return new OrderSplitGateResult(
            failed: $failed,
            erpWarning: $this->erpWarning($order),
        );
    }

    /**
     * Paid when the order is or was `payment-received`, or a successful capture exists.
     *
     * Bank transfer records payment as a status change and then often moves on, with no capture row.
     */
    private function orderIsPaid(Order $order): bool
    {
        if ($order->status === 'payment-received' || $this->orderWasPaymentReceived($order)) {
            return true;
        }

        return $order->transactions()
            ->where('type', 'capture')
            ->where('success', true)
            ->exists();
    }

    private function orderWasPaymentReceived(Order $order): bool
    {
        return $order->getActivitiesByStatuses(['payment-received'])->isNotEmpty();
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
     * Sending is enabled, the ERP timestamp is empty, and the status is outside the pre-send list.
     *
     * `payment-offline` is not treated as already sent. The warning is not a failure.
     */
    private function erpWarning(Order $order): bool
    {
        if (filled($this->metaValue($order, 'erp_sent_at')) || ! $this->orderSendingIsEnabled()) {
            return false;
        }

        return ! in_array($order->status, self::PRE_SEND_STATUSES, true);
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
