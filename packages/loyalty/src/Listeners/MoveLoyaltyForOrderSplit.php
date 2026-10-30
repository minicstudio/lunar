<?php

namespace Lunar\Loyalty\Listeners;

use Lunar\Events\OrderSplitLoyaltyMoving;
use Lunar\Loyalty\Exceptions\LoyaltySplitRejectedException;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;

/**
 * Move an existing order spend onto the one new order that receives the redemption.
 *
 * Runs synchronously inside the split transaction. It does not spend the points a second time.
 */
final class MoveLoyaltyForOrderSplit
{
    public function __construct(
        protected LoyaltyLedger $ledger,
    ) {}

    public function handle(OrderSplitLoyaltyMoving $event): void
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return;
        }

        $spend = $this->ledger->findByEventKey(LoyaltyEventKey::orderSpend($event->original->id));

        if (! $event->applied) {
            if ($spend) {
                throw new LoyaltySplitRejectedException(__('lunarpanel.loyalty::exceptions.split.spent_must_apply_to_one'));
            }

            return;
        }

        if (! $spend) {
            return;
        }

        if (! $event->chosen) {
            throw new LoyaltySplitRejectedException(__('lunarpanel.loyalty::exceptions.split.redemption_has_no_order'));
        }

        $accepted = (int) ($event->chosen->meta['loyalty_points_to_redeem'] ?? 0);

        if ($accepted < (int) $spend->points) {
            throw new LoyaltySplitRejectedException(__('lunarpanel.loyalty::exceptions.split.order_cannot_accept_redemption'));
        }

        $meta = $spend->meta instanceof \ArrayObject
            ? $spend->meta->getArrayCopy()
            : (array) ($spend->meta ?? []);
        $meta['moved_from_order_id'] = $event->original->id;
        $meta['reason'] = 'order_split';

        $spend->update([
            'reference_type' => $event->chosen->getMorphClass(),
            'reference_id' => $event->chosen->id,
            'event_key' => LoyaltyEventKey::orderSpend($event->chosen->id),
            'meta' => $meta,
        ]);
    }
}
