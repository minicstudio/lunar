<?php

namespace Lunar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Lunar\Models\Order;

/**
 * Dispatched synchronously inside the order transaction after product lines are recalculated.
 *
 * Listeners must not be queued. A queued listener would run after commit, so a failed save could not undo it.
 */
class OrderProductLinesRecalculated
{
    use Dispatchable;

    public function __construct(
        public Order $order,
        public int $previousPoints,
    ) {}
}
