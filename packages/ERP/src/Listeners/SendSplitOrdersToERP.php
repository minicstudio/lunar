<?php

namespace Lunar\ERP\Listeners;

use Lunar\ERP\Events\SplitOfflineOrderReadyForErp;
use Lunar\ERP\Services\ErpService;
use Lunar\Events\OrdersSplit;
use Throwable;

/**
 * Queue the placement ERP send for offline-payment orders created by a split.
 *
 * Card and bank-transfer orders wait for payment. Each offline order is handed to
 * SendOrderToERP, which retries on the ERP queue and moves the order to failed-erp-sync
 * after the last failure. A send failure does not delete the new orders.
 */
class SendSplitOrdersToERP
{
    /**
     * @var list<string>
     */
    private const OFFLINE_PAYMENT_OPTIONS = [
        'cash-on-delivery',
        'cash-in-hand',
        'offline',
    ];

    public function handle(OrdersSplit $event): void
    {
        if (! config('lunar.erp.enabled')) {
            return;
        }

        $erpService = app(ErpService::class);

        if ($erpService->getEnabledProviders() === []) {
            return;
        }

        foreach ($event->orders as $order) {
            $order = $order->fresh() ?? $order;
            $paymentOption = $order->meta['payment_option'] ?? null;

            if (! in_array($paymentOption, self::OFFLINE_PAYMENT_OPTIONS, true)) {
                continue;
            }

            try {
                event(new SplitOfflineOrderReadyForErp($order));
            } catch (Throwable $exception) {
                // A synchronous queue marks the order failed, then rethrows. The other new orders still need to be queued.
                $current = $order->fresh();

                if ($current === null || $current->status !== 'failed-erp-sync') {
                    report($exception);
                }
            }
        }
    }
}
