<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Enums\ProductEventType;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Product;

class SyncProductOnCollectionsUpdated
{
    public function handle(object $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            return;
        }

        $product = $event->model ?? null;

        if (! $product instanceof Product) {
            return;
        }

        KlaviyoLogger::debug('Product collections updated listener dispatching SyncProductToKlaviyo', [
            'product_id' => $product->id,
        ]);

        dispatch(SyncProductToKlaviyo::fromProduct($product, ProductEventType::UPDATE));
    }
}
