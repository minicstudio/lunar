<?php

namespace Lunar\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Lunar\Models\Order;

/**
 * Dispatched once, after the split transaction commits, with the new orders.
 */
class OrdersSplit
{
    use Dispatchable;

    /**
     * @param  list<Order>  $orders
     */
    public function __construct(
        public array $orders,
    ) {}
}
