<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Klaviyo\Concerns\ResolvesDiscountables;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Discount;
use Lunar\Models\Product;

class SyncProductsOnDiscountLimitationChanged
{
    use ResolvesDiscountables;

    public function handle(object $event): void
    {
        if (! $this->klaviyoCatalogSyncEnabled()) {
            return;
        }

        $discount = $event->discount ?? null;

        if (! $discount instanceof Discount) {
            return;
        }

        if ($this->discountHasCoupon($discount)) {
            return;
        }

        $data = is_array($event->data ?? null) ? $event->data : [];

        if (! isset($data['discountable_type'], $data['discountable_id'])) {
            // Brand/variant/customer RMs omit event data — sync current discount scope.
            $productIds = $this->getAffectedProductIds($discount);

            if ($productIds->isEmpty()) {
                return;
            }

            KlaviyoLogger::debug('Discount limitation changed without discountable data — syncing affected products', [
                'discount_id' => $discount->id,
                'product_count' => $productIds->count(),
            ]);

            $this->dispatchProductSyncs($productIds);

            return;
        }

        $type = (string) $data['discountable_type'];
        $id = (int) $data['discountable_id'];

        if (is_a($type, Product::class, true)) {
            KlaviyoLogger::debug('Discount limitation changed for product — syncing product', [
                'discount_id' => $discount->id,
                'product_id' => $id,
            ]);

            $this->dispatchProductSyncs([$id]);

            return;
        }

        $productIds = $this->resolveProductsFromDiscountRelation($type, $id);

        if ($productIds === []) {
            return;
        }

        KlaviyoLogger::debug('Discount limitation changed — syncing related products', [
            'discount_id' => $discount->id,
            'discountable_type' => $type,
            'discountable_id' => $id,
            'product_count' => count($productIds),
        ]);

        $this->dispatchProductSyncs($productIds);
    }
}
