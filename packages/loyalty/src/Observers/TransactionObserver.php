<?php

namespace Lunar\Loyalty\Observers;

use Lunar\Loyalty\Services\LoyaltyEngine;
use Lunar\Models\Transaction;

class TransactionObserver
{
    public function __construct(
        protected LoyaltyEngine $engine,
    ) {}

    /**
     * Handle the transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return;
        }

        if ($transaction->type !== 'refund') {
            return;
        }

        $order = $transaction->order;

        if (! $order) {
            return;
        }

        $refundNumber = $order->refunds()->count();

        $this->engine->adjustForRefund($order, $transaction, $refundNumber);
    }
}
