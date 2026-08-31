<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Klaviyo\Concerns\ResolvesDiscountables;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Discount;

class SyncProductsOnDiscountBecameLimited
{
    use ResolvesDiscountables;

    /**
     * When the first limitation is attached to a formerly global discount, every
     * product that previously had the sale price must be re-synced.
     */
    public function handle(object $event): void
    {
        if (! KlaviyoAvailability::catalogSyncEnabled()) {
            return;
        }

        $discount = $event->discount ?? null;

        if (! $discount instanceof Discount) {
            return;
        }

        if ($this->discountHasCoupon($discount)) {
            return;
        }

        if ($discount->discountables->isEmpty()
            && $discount->brands->isEmpty()
            && $discount->collections->isEmpty()
        ) {
            KlaviyoLogger::debug('Discount became limited — dispatching full Klaviyo catalog sync', [
                'discount_id' => $discount->id,
            ]);

            $this->dispatchFullCatalogSync();
        }
    }
}
