<?php

namespace Lunar\Mailchimp\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lunar\ERP\Events\OrderPlacedEvent as EventsOrderPlacedEvent;
use Lunar\Mailchimp\Jobs\SyncOrderToMailchimp as SyncOrderJob;

class SyncOrderOnPlacement implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(EventsOrderPlacedEvent $event): void
    {
        if (! config('lunar.mailchimp.enabled', false)) {
            return;
        }

        $order = $event->order;

        // Sync order to Ecommerce API
        if (config('lunar.mailchimp.sync_orders', true)) {
            SyncOrderJob::dispatch($order);
        }
    }
}
