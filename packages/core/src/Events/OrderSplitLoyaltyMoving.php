<?php

namespace Lunar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Lunar\Models\Order;

/**
 * Dispatched synchronously inside the split transaction after the chosen order has its loyalty meta.
 *
 * Listeners must not be queued. A queued listener would run after commit, so a failed save could not undo it.
 */
class OrderSplitLoyaltyMoving
{
    use Dispatchable;

    public function __construct(
        public Order $original,
        public ?Order $chosen,
        public bool $applied,
    ) {}
}
