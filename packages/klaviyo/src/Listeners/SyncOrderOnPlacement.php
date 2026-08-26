<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\Klaviyo\Jobs\SyncOrderToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncOrderOnPlacement
{
    public function handle(OrderPlacedEvent $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_orders', false)) {
            KlaviyoLogger::debug('Order listener skipped — enabled or sync_orders off', [
                'order_id' => $event->order->id,
                'enabled' => (bool) config('lunar.klaviyo.enabled', false),
                'sync_orders' => (bool) config('lunar.klaviyo.sync_orders', false),
            ]);

            return;
        }

        KlaviyoLogger::debug('Order listener dispatching SyncOrderToKlaviyo', [
            'order_id' => $event->order->id,
        ]);

        SyncOrderToKlaviyo::dispatch($event->order);
    }
}
