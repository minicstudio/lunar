<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Enums\ProductEventType;
use Lunar\Events\ProductUpdatedEvent;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncProductOnUpdated
{
    public function handle(ProductUpdatedEvent $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            KlaviyoLogger::debug('Product updated listener skipped — enabled or sync_products off', [
                'product_id' => $event->product->id,
            ]);

            return;
        }

        // ProductPublished also fires on status→published; unique job id coalesces duplicates.
        if ($event->product->wasChanged('status') && $event->product->status === 'published') {
            KlaviyoLogger::debug('Product updated listener skipped — publish handled by ProductPublished', [
                'product_id' => $event->product->id,
            ]);

            return;
        }

        KlaviyoLogger::debug('Product updated listener dispatching SyncProductToKlaviyo', [
            'product_id' => $event->product->id,
        ]);

        dispatch(SyncProductToKlaviyo::fromProduct($event->product, ProductEventType::UPDATE));
    }
}
