<?php

namespace Lunar\Loyalty\Listeners;

use Illuminate\Support\Str;
use Lunar\Events\OrderProductLinesRecalculated;
use Lunar\Loyalty\Services\LoyaltyAccountManager;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;

/**
 * Adjust an existing order spend when a product-line edit changes the redeemed points.
 *
 * Runs synchronously on the order transaction. It does not call spendForOrder().
 */
final class AdjustLoyaltyForOrderProductLineEdit
{
    public function __construct(
        protected LoyaltyLedger $ledger,
        protected LoyaltyAccountManager $accounts,
    ) {}

    public function handle(OrderProductLinesRecalculated $event): void
    {
        if (! config('lunar.loyalty.enabled', true)) {
            return;
        }

        $order = $event->order;
        $current = (int) ($order->meta['loyalty_points_to_redeem'] ?? 0);
        $difference = $event->previousPoints - $current;

        if ($difference === 0 || ! $order->customer_id) {
            return;
        }

        $spend = $this->ledger->findByEventKey(LoyaltyEventKey::orderSpend($order->id));

        if (! $spend) {
            return;
        }

        $customer = $order->customer;

        if (! $customer) {
            return;
        }

        $account = $this->accounts->findForCustomer($customer);

        if (! $account) {
            return;
        }

        $options = [
            'reference' => $order,
            'meta' => ['reason' => 'order_product_lines_edited'],
        ];

        if ($difference > 0) {
            $options['spend_transaction'] = $spend;
        }

        $this->ledger->adjust(
            $account,
            $difference,
            'order:'.$order->id.':product-lines:'.Str::uuid()->toString(),
            $options,
        );
    }
}
