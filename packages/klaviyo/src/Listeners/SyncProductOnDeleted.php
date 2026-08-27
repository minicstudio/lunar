<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Enums\ProductEventType;
use Lunar\Events\ProductDeletedEvent;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Support\CatalogExternalIdStore;
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

        $product = $event->product;
        $catalogService = app(KlaviyoCatalogService::class);

        // Prefer ids captured before variants were removed; fall back to store / product id.
        $captured = $catalogService->captureExternalIdsForProductId(
            $product->id,
            CatalogExternalIdStore::get($product->id),
            [(string) $product->id],
        );

        $itemExternalId = $captured[0] ?? (string) $product->id;
        $additional = array_values(array_filter(
            $captured,
            fn (string $id) => $id !== $itemExternalId
        ));

        KlaviyoLogger::debug('Product deleted listener dispatching SyncProductToKlaviyo', [
            'product_id' => $product->id,
            'item_external_id' => $itemExternalId,
            'additional_external_ids' => $additional,
        ]);

        SyncProductToKlaviyo::dispatch(
            productId: $product->id,
            eventType: ProductEventType::DELETE,
            itemExternalId: $itemExternalId,
            additionalExternalIds: $additional,
        );
    }
}
