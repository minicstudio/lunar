<?php

namespace Lunar\Admin\Support;

use Lunar\DataTypes\Price;
use Lunar\Models\Order;

class OrderDiscountBreakdown
{
    /**
     * Build discount breakdown lines from the order's stored discount breakdown.
     *
     * @return array<int, OrderDiscountBreakdownLine>
     */
    public static function fromOrder(Order $order): array
    {
        return collect($order->discount_breakdown ?? [])
            ->map(function ($breakdown) use ($order) {
                $discount = $breakdown->discount;
                $hasCoupon = filled($discount->coupon ?? null);

                $type = $hasCoupon
                    ? __('lunarpanel::order.infolist.discount_breakdown.coupon')
                    : __('lunarpanel::order.infolist.discount_breakdown.discount');

                $detail = $discount->name ?? ($hasCoupon ? $discount->coupon : null);

                return new OrderDiscountBreakdownLine(
                    type: $type,
                    detail: $detail,
                    amount: $breakdown->total instanceof Price
                        ? $breakdown->total
                        : new Price((int) $breakdown->total, $order->currency, 1),
                );
            })
            ->values()
            ->all();
    }
}
