<?php

namespace Lunar\Klaviyo\Listeners;

use Illuminate\Support\Collection;
use Lunar\Klaviyo\Concerns\ResolvesDiscountables;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncProductsOnDiscountDeleted
{
    use ResolvesDiscountables;

    public function handle(object $event): void
    {
        if (! KlaviyoAvailability::catalogSyncEnabled()) {
            return;
        }

        $data = is_array($event->data ?? null) ? $event->data : [];
        $products = Collection::make($data['products'] ?? []);
        $collections = Collection::make($data['collections'] ?? []);

        if ($products->isEmpty() && $collections->isEmpty()) {
            KlaviyoLogger::debug('Discount deleted (was global) — dispatching full Klaviyo catalog sync');

            $this->dispatchFullCatalogSync();

            return;
        }

        $productIds = $products->pluck('discountable_id')->unique()->filter()->values();

        KlaviyoLogger::debug('Discount deleted — syncing previously related products', [
            'product_count' => $productIds->count(),
            'collection_count' => $collections->count(),
        ]);

        $this->dispatchProductSyncs($productIds);

        foreach ($collections as $collection) {
            if (! isset($collection['discountable_type'], $collection['discountable_id'])) {
                continue;
            }

            $relatedIds = $this->resolveProductsFromDiscountRelation(
                (string) $collection['discountable_type'],
                (int) $collection['discountable_id']
            );

            $this->dispatchProductSyncs($relatedIds);
        }
    }
}
