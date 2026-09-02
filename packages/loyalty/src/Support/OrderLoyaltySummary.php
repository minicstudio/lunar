<?php

namespace Lunar\Loyalty\Support;

use Lunar\Models\Order;

final class OrderLoyaltySummary
{
    /**
     * Resolve loyalty redemption details for an order.
     *
     * @return array{points: int, discount_minor: int}|null
     */
    public static function fromOrder(Order $order): ?array
    {
        $meta = $order->meta?->toArray() ?? [];
        $points = (int) ($meta['loyalty_points_to_redeem'] ?? 0);
        $discountMinor = (int) ($meta['loyalty_discount_minor'] ?? 0);

        if ($points > 0) {
            return [
                'points' => $points,
                'discount_minor' => $discountMinor > 0
                    ? $discountMinor
                    : self::discountMinorFromPoints($points),
            ];
        }

        $transaction = $order->loyaltySpendTransaction;

        if (! $transaction || $transaction->points <= 0) {
            return null;
        }

        return [
            'points' => $transaction->points,
            'discount_minor' => $discountMinor > 0
                ? $discountMinor
                : self::discountMinorFromPoints($transaction->points),
        ];
    }

    /**
     * Calculate the discount amount in minor units from points.
     */
    protected static function discountMinorFromPoints(int $points): int
    {
        $ratio = max(1, (int) config('lunar.loyalty.currency.redeem_ratio', 1));

        return $points * $ratio;
    }
}
