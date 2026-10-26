<?php

namespace Lunar\ERP\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;

class OrderInvoiceGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $source  Billing provider value or `lunar`
     */
    public function __construct(
        public Order $order,
        public string $source,
    ) {}
}
