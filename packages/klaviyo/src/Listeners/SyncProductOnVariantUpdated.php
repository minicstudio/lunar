<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Enums\ProductEventType;
use Lunar\Events\ProductVariantUpdatedEvent;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Support\CatalogExternalIdStore;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncProductOnVariantUpdated
{
    public function handle(ProductVariantUpdatedEvent $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            return;
        }

        $variant = $event->productVariant;
        $product = $variant->product;

        if (! $product) {
            return;
        }

        $sku = trim((string) ($variant->sku ?? ''));

        if ($sku !== '') {
            CatalogExternalIdStore::remember($product->id, str_replace('/', '-', $sku));
        }

        KlaviyoLogger::debug('Variant updated listener dispatching SyncProductToKlaviyo', [
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ]);

        dispatch(SyncProductToKlaviyo::fromProduct($product, ProductEventType::UPDATE));
    }
}
