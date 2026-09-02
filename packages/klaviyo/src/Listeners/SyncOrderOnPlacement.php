<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\Klaviyo\Jobs\SyncOrderToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncOrderOnPlacement
{
    public function handle(OrderPlacedEvent $event): void
    {
        if (! KlaviyoAvailability::orderSyncEnabled()) {
            KlaviyoLogger::debug('Order listener skipped — enabled or sync_orders off', [
                'order_id' => $event->order->id,
                'enabled' => KlaviyoAvailability::enabled(),
                'sync_orders' => KlaviyoAvailability::syncOrders(),
            ]);

            return;
        }

        KlaviyoLogger::debug('Order listener dispatching SyncOrderToKlaviyo', [
            'order_id' => $event->order->id,
        ]);

        dispatch(new SyncOrderToKlaviyo($event->order))->onConnection(config('lunar.klaviyo.queue_connection', 'deferred'));
    }
}
