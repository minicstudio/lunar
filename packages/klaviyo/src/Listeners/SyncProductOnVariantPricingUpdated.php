<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Enums\ProductEventType;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\ProductVariant;

class SyncProductOnVariantPricingUpdated
{
    public function handle(object $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            return;
        }

        $variant = $event->model ?? null;

        if (! $variant instanceof ProductVariant) {
            return;
        }

        $product = $variant->product;

        if (! $product) {
            return;
        }

        KlaviyoLogger::debug('Variant pricing updated listener dispatching SyncProductToKlaviyo', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ]);

        dispatch(SyncProductToKlaviyo::fromProduct($product, ProductEventType::UPDATE));
    }
}
