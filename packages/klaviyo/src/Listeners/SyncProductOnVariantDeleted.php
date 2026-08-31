<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Events\ProductVariantDeletedEvent;
use Lunar\Klaviyo\Jobs\DeleteCatalogVariantFromKlaviyo;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Support\CatalogExternalIdStore;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncProductOnVariantDeleted
{
    public function handle(ProductVariantDeletedEvent $event): void
    {
        if (! KlaviyoAvailability::catalogSyncEnabled()) {
            return;
        }

        $variant = $event->productVariant;
        $productId = $variant->product_id ? (int) $variant->product_id : null;
        $variantExternalId = app(KlaviyoCatalogService::class)->resolveVariantExternalId($variant);

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

        dispatch(new DeleteCatalogVariantFromKlaviyo(
            variantExternalId: $variantExternalId,
            productId: $productId,
        ))->onConnection(config('lunar.klaviyo.queue_connection', 'deferred'));
    }
}
