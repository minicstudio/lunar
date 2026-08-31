<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Enums\ProductEventType;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Product;

class SyncProductOnMediaUpdated
{
    public function handle(object $event): void
    {
        if (! KlaviyoAvailability::catalogSyncEnabled()) {
            return;
        }

        $product = $event->model ?? null;

        if (! $product instanceof Product) {
            return;
        }

        KlaviyoLogger::debug('Product media updated listener dispatching SyncProductToKlaviyo', [
            'product_id' => $product->id,
        ]);

        dispatch(SyncProductToKlaviyo::fromProduct($product, ProductEventType::UPDATE))->onConnection(config('lunar.klaviyo.queue_connection', 'deferred'));
    }
}
