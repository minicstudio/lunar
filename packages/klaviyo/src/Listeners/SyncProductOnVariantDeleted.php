<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Events\ProductVariantDeletedEvent;
use Lunar\Klaviyo\Jobs\DeleteCatalogVariantFromKlaviyo;
use Lunar\Klaviyo\Support\CatalogExternalIdStore;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncProductOnVariantDeleted
{
    public function handle(ProductVariantDeletedEvent $event): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            return;
        }

        $variant = $event->productVariant;
        $productId = $variant->product_id ? (int) $variant->product_id : null;
        $variantExternalId = (string) $variant->id;

        // Capture item identity from this variant's SKU before it is gone (pre-delete path).
        if ($productId) {
            $sku = trim((string) ($variant->sku ?? ''));

            if ($sku !== '') {
                CatalogExternalIdStore::rememberIfAbsent($productId, str_replace('/', '-', $sku));
            }
        }

        KlaviyoLogger::debug('Variant deleted listener dispatching DeleteCatalogVariantFromKlaviyo', [
            'product_id' => $productId,
            'variant_id' => $variant->id,
        ]);

        DeleteCatalogVariantFromKlaviyo::dispatch(
            variantExternalId: $variantExternalId,
            productId: $productId,
        );
    }
}
