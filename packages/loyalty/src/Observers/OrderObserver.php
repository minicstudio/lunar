<?php

namespace Lunar\Loyalty\Observers;

use Illuminate\Support\Facades\Log;
use Lunar\Loyalty\Services\LoyaltyEngine;
use Lunar\Models\Order;

class OrderObserver
{
    public function __construct(
        protected LoyaltyEngine $engine,
    ) {}

    /**
     * Handle the order "updated" event.
     */
    public function updated(Order $order): void
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return;
        }

        if (! $order->wasChanged('status')) {
            return;
        }

        $earnStatus = config('lunar.loyalty.earn.order_status', 'completed');

        if ($order->status === $earnStatus) {
            Log::info('Earning from order', ['order_id' => $order->id]);
            $this->engine->earnFromOrder($order);
        }

        $cancelStatuses = config('lunar.loyalty.cancel.statuses', ['canceled']);

        if (in_array($order->status, $cancelStatuses, true)) {
            $this->engine->reverseSpendForCancelledOrder($order);
            $this->engine->reverseEarnForCancelledOrder($order);
        }
    }
}
