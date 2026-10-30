<?php

namespace Lunar\ERP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;

/**
 * An offline-payment order created by a split is ready for the same queued ERP send as a placement.
 *
 * This is not OrderPlacedEvent. Placement listeners such as Klaviyo and Mailchimp stay on that event.
 */
class SplitOfflineOrderReadyForErp
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
    ) {}
}
