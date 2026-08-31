<?php

namespace Lunar\Klaviyo\Listeners;

use Lunar\Klaviyo\Concerns\ResolvesDiscountables;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Discount;

class SyncProductsOnDiscountBecameGlobal
{
    use ResolvesDiscountables;

    /**
     * When the last limitation is removed, the discount applies to all products again.
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
            KlaviyoLogger::debug('Discount became global — dispatching full Klaviyo catalog sync', [
                'discount_id' => $discount->id,
            ]);

            $this->dispatchFullCatalogSync();
        }
    }
}
