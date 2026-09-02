<?php

namespace Lunar\Loyalty\Facades;

use Illuminate\Support\Facades\Facade;
use Lunar\Loyalty\Services\LoyaltyEngine;

/**
 * @method static void earnFromOrder(\Lunar\Models\Order $order)
 * @method static void earnFromRegistration(\Lunar\Models\Customer $customer)
 * @method static void earnFromBirthday(\Lunar\Models\Customer $customer, int $year)
 * @method static \Lunar\Loyalty\Models\LoyaltyTransaction|null spendForOrder(\Lunar\Models\Order $order, int $points)
 * @method static void reverseSpendForCancelledOrder(\Lunar\Models\Order $order)
 * @method static void adjustForRefund(\Lunar\Models\Order $order, \Lunar\Models\Transaction $refund, int $refundNumber)
 * @method static \Lunar\Loyalty\Models\LoyaltyTransaction|null manualAdjust(\Lunar\Loyalty\Models\LoyaltyAccount $account, int $points, string $reason)
 * @method static int estimateOrderPoints(\Lunar\Models\Order $order)
 * @method static int estimateCartPoints(\Lunar\Models\Contracts\Cart|\Lunar\Models\Cart $cart)
 *
 * @see \Lunar\Loyalty\Services\LoyaltyEngine
 */
class Loyalty extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return LoyaltyEngine::class;
    }
}
