<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Enums\ProductEventType;
use Lunar\Events\ProductDeletedEvent;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncProductOnDeleted
{
    public function handle(ProductDeletedEvent $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            KlaviyoLogger::debug('Product deleted listener skipped — enabled or sync_products off', [
                'product_id' => $event->product->id,
            ]);

            return;
        }

        KlaviyoLogger::debug('Product deleted listener dispatching SyncProductToKlaviyo', [
            'product_id' => $event->product->id,
        ]);

        SyncProductToKlaviyo::dispatch($event->product, ProductEventType::DELETE);
    }
}
