<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Enums\ProductEventType;
use Lunar\Events\ProductPublished;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncProductOnPublished
{
    public function handle(ProductPublished $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            KlaviyoLogger::debug('Product published listener skipped — enabled or sync_products off', [
                'product_id' => $event->product->id,
            ]);

            return;
        }

        KlaviyoLogger::debug('Product published listener dispatching SyncProductToKlaviyo', [
            'product_id' => $event->product->id,
        ]);

        dispatch(SyncProductToKlaviyo::fromProduct($event->product, ProductEventType::CREATE))->onConnection(config('lunar.klaviyo.queue_connection', 'deferred'));
    }
}
