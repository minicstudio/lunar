<?php

namespace Lunar\ERP\Support;

use Lunar\Admin\Support\Actions\Traits\UpdatesOrderStatus;
use Lunar\Models\Order;

class OrderStatusUpdater
{
    use UpdatesOrderStatus;

    /**
     * Public wrapper for the protected updateStatus() method.
     */
    public function handle(Order $order, array $data): void
    {
        // call the trait’s protected method from within the same class context
        $this->updateStatus($order, $data);
    }
}
