<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Support\CatalogExternalIdStore;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Product;

/**
 * Runs on Product deleting while variants still exist (or are about to be deleted).
 * Persists catalog item external_id so ProductDeletedEvent / DELETE jobs do not
 * need to re-resolve SKU from removed variants.
 */
class CaptureCatalogIdentityOnProductDeleting
{
    public function handle(Product $product): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            return;
        }

        try {
            // Prefer already-loaded / soft-deleted variants if the core observer ran first.
            $product->load(['variants' => fn ($query) => $query->withTrashed()]);
            $externalId = app(KlaviyoCatalogService::class)->resolveItemExternalId($product);
            CatalogExternalIdStore::remember($product->id, $externalId);

            KlaviyoLogger::debug('Captured catalog identity on product deleting', [
                'product_id' => $product->id,
                'item_external_id' => $externalId,
            ]);
        } catch (\Throwable $e) {
            KlaviyoLogger::warning('Failed to capture catalog identity on product deleting', [
                'product_id' => $product->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
