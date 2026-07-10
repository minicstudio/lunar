<?php

namespace Lunar\Loyalty\Support;

use Illuminate\Support\Str;

final class LoyaltyEventKey
{
    /**
     * Build an earn event key for an order.
     */
    public static function orderEarn(int $orderId): string
    {
        return "order:{$orderId}:earn";
    }

    /**
     * Build a spend event key for an order.
     */
    public static function orderSpend(int $orderId): string
    {
        return "order:{$orderId}:spend";
    }

    /**
     * Build a cancel spend reversal event key for an order.
     */
    public static function orderCancelSpend(int $orderId): string
    {
        return "order:{$orderId}:cancel:spend";
    }

    /**
     * Build a cancel earn reversal event key for an order.
     */
    public static function orderCancelEarn(int $orderId): string
    {
        return "order:{$orderId}:cancel:earn";
    }

    /**
     * Build a refund event key for an order.
     */
    public static function orderRefund(int $orderId, int $refundNumber): string
    {
        return "order:{$orderId}:refund:{$refundNumber}";
    }

    /**
     * Build a registration bonus event key for a customer.
     */
    public static function customerRegistration(int $customerId): string
    {
        return "customer:{$customerId}:registration";
    }

    /**
     * Build a birthday reward event key for a customer.
     */
    public static function customerBirthday(int $customerId, int $year): string
    {
        return "customer:{$customerId}:birthday:{$year}";
    }

    /**
     * Build a manual adjust event key.
     */
    public static function adjust(): string
    {
        return 'adjust:'.Str::uuid()->toString();
    }
}
