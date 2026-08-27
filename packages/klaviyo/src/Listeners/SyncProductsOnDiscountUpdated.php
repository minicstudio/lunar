<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Events\DiscountUpdatedEvent;
use Lunar\Klaviyo\Concerns\ResolvesDiscountables;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class SyncProductsOnDiscountUpdated
{
    use ResolvesDiscountables;

    /**
     * Fields that don't affect catalog prices.
     *
     * @var list<string>
     */
    protected array $irrelevantFields = ['uses', 'updated_at'];

    public function handle(DiscountUpdatedEvent $event): void
    {
        if (! $this->klaviyoCatalogSyncEnabled()) {
            return;
        }

        $discount = $event->discount;

        if ($this->discountHasCoupon($discount)) {
            return;
        }

        $changedFields = array_keys($discount->getChanges());
        $relevantChanges = array_diff($changedFields, $this->irrelevantFields);

        if ($relevantChanges === []) {
            return;
        }

        if ($this->isGlobalDiscount($discount)) {
            KlaviyoLogger::debug('Discount updated (global) — dispatching full Klaviyo catalog sync', [
                'discount_id' => $discount->id,
                'changed' => array_values($relevantChanges),
            ]);

            $this->dispatchFullCatalogSync();

            return;
        }

        $productIds = $this->getAffectedProductIds($discount);

        if ($productIds->isEmpty()) {
            return;
        }

        KlaviyoLogger::debug('Discount updated — dispatching Klaviyo product syncs', [
            'discount_id' => $discount->id,
            'product_count' => $productIds->count(),
            'changed' => array_values($relevantChanges),
        ]);

        $this->dispatchProductSyncs($productIds);
    }
}
