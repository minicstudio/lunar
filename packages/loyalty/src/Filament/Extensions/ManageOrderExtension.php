<?php

namespace Lunar\Loyalty\Filament\Extensions;

use Lunar\Admin\Support\Extending\ViewPageExtension;
use Lunar\Admin\Support\OrderDiscountBreakdownLine;
use Lunar\DataTypes\Price;
use Lunar\Loyalty\Support\OrderLoyaltySummary;
use Lunar\Models\Order;

class ManageOrderExtension extends ViewPageExtension
{
    /**
     * Append loyalty redemption to the order discount breakdown.
     *
     * @param  array<int, OrderDiscountBreakdownLine>  $lines
     * @return array<int, OrderDiscountBreakdownLine>
     */
    public function extendOrderDiscountBreakdownLines(array $lines, Order $order): array
    {
        $summary = OrderLoyaltySummary::fromOrder($order);

        if (! $summary) {
            return $lines;
        }

        $lines[] = new OrderDiscountBreakdownLine(
            type: __('lunarpanel.loyalty::plugin.order.loyalty_points'),
            detail: __('lunarpanel.loyalty::plugin.order.loyalty_points_suffix', [
                'points' => number_format($summary['points']),
            ]),
            amount: new Price($summary['discount_minor'], $order->currency, 1),
        );

        return $lines;
    }

    /**
     * Hide loyalty redemption meta keys from the additional info section.
     *
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    public function extendHiddenOrderMetaKeys(array $keys): array
    {
        return [
            ...$keys,
            'loyalty_points_to_redeem',
            'loyalty_discount_minor',
        ];
    }
}
