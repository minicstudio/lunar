<?php

namespace Lunar\Loyalty\Services;

use Illuminate\Support\Facades\Log;
use Lunar\DataTypes\Price;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Models\LoyaltyTransaction;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Cart as CartContract;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Models\Transaction;

final class LoyaltyEngine
{
    public function __construct(
        protected LoyaltyLedger $ledger,
        protected LoyaltyAccountManager $accountManager,
    ) {}

    /**
     * Award points for a completed order.
     */
    public function earnFromOrder(Order $order): void
    {
        if (! $this->isEnabled() || ! $order->customer_id) {
            return;
        }

        $points = $this->estimateOrderPoints($order);

        Log::info('Earning from order', ['order_id' => $order->id, 'points' => $points]);

        if ($points <= 0) {
            return;
        }

        $account = $this->accountManager->firstOrCreateForCustomer($order->customer);

        $this->ledger->earn(
            $account,
            $points,
            LoyaltyEventKey::orderEarn($order->id),
            ['reference' => $order]
        );
    }

    /**
     * Award registration bonus points to a customer.
     */
    public function earnFromRegistration(Customer $customer): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $config = config('lunar.loyalty.events.registration', []);
        $calculator = app($config['calculator']);

        $points = $calculator->calculate([
            'points' => (int) ($config['points'] ?? 0),
        ]);

        if ($points <= 0) {
            return;
        }

        $account = $this->accountManager->firstOrCreateForCustomer($customer);

        $this->ledger->earn(
            $account,
            $points,
            LoyaltyEventKey::customerRegistration($customer->id),
            ['reference' => $customer]
        );
    }

    /**
     * Award birthday points to a customer.
     */
    public function earnFromBirthday(Customer $customer, int $year): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $config = config('lunar.loyalty.scheduled_rewards.birthday', []);
        $points = (int) ($config['points'] ?? 0);

        if ($points <= 0) {
            return;
        }

        $account = $this->accountManager->firstOrCreateForCustomer($customer);

        $this->ledger->earn(
            $account,
            $points,
            LoyaltyEventKey::customerBirthday($customer->id, $year),
            ['reference' => $customer]
        );
    }

    /**
     * Spend points at order creation.
     */
    public function spendForOrder(Order $order, int $points): ?LoyaltyTransaction
    {
        if (! $this->isEnabled() || $points <= 0 || ! $order->customer_id) {
            return null;
        }

        $account = $this->accountManager->firstOrCreateForCustomer($order->customer);

        return $this->ledger->spend(
            $account,
            $points,
            LoyaltyEventKey::orderSpend($order->id),
            ['reference' => $order]
        );
    }

    /**
     * Reverse spend on order cancellation.
     */
    public function reverseSpendForCancelledOrder(Order $order): void
    {
        if (! $this->isEnabled() || ! config('lunar.loyalty.cancel.reverse_spend', true)) {
            return;
        }

        $spendTransaction = $this->ledger->findByEventKey(LoyaltyEventKey::orderSpend($order->id));

        if (! $spendTransaction || ! $order->customer_id) {
            return;
        }

        $account = $this->accountManager->firstOrCreateForCustomer($order->customer);

        $this->ledger->adjust(
            $account,
            $spendTransaction->points,
            LoyaltyEventKey::orderCancelSpend($order->id),
            [
                'reference' => $order,
                'spend_transaction' => $spendTransaction,
                'meta' => ['reason' => 'order_cancelled'],
            ]
        );
    }

    /**
     * Claw back earned points on a refund.
     */
    public function adjustForRefund(Order $order, Transaction $refund, int $refundNumber): void
    {
        if (! $this->isEnabled() || ! $order->customer_id) {
            return;
        }

        $earnTransaction = $this->ledger->findByEventKey(LoyaltyEventKey::orderEarn($order->id));
        $earnedPoints = $earnTransaction?->points ?? 0;

        if ($earnedPoints <= 0) {
            return;
        }

        $orderTotalMinor = $this->toMinorAmount($order->total);
        $refundAmountMinor = $this->toMinorAmount($refund->amount);

        if ($orderTotalMinor <= 0 || $refundAmountMinor <= 0) {
            return;
        }

        $pointsToReverse = (int) floor(($refundAmountMinor / $orderTotalMinor) * $earnedPoints);

        // Cap to what remains in the earn lot — the customer may have already spent some
        // or the earn may have been reversed by a prior cancellation.
        $lotRemaining = (int) ($earnTransaction->remaining_points ?? 0);
        $pointsToReverse = min($pointsToReverse, $lotRemaining);

        if ($pointsToReverse <= 0) {
            return;
        }

        $account = $this->accountManager->firstOrCreateForCustomer($order->customer);

        $this->ledger->adjust(
            $account,
            -$pointsToReverse,
            LoyaltyEventKey::orderRefund($order->id, $refundNumber),
            [
                'reference' => $order,
                'earn_transaction' => $earnTransaction,
                'meta' => [
                    'reason' => 'refund',
                    'refund_transaction_id' => $refund->id,
                    'refund_amount_minor' => $refundAmountMinor,
                    'order_total_minor' => $orderTotalMinor,
                    'earned_points' => $earnedPoints,
                ],
            ]
        );
    }

    /**
     * Expire points for a single earn lot.
     */
    public function expireLot(LoyaltyTransaction $earnLot): ?LoyaltyTransaction
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $eventKey = "expire:{$earnLot->id}";

        return $this->ledger->expire($earnLot, $eventKey);
    }

    /**
     * Perform a manual staff adjust.
     *
     * Positive credits use earn lots so they remain spendable and do not inflate lifetime counters.
     * Negative debits write a signed adjust transaction via FIFO — lifetime_spent is not updated.
     */
    public function manualAdjust(LoyaltyAccount $account, int $points, string $reason): ?LoyaltyTransaction
    {
        if (! $this->isEnabled() || $points === 0) {
            return null;
        }

        $meta = ['reason' => $reason, 'manual' => true];
        $eventKey = LoyaltyEventKey::adjust();

        if ($points > 0) {
            return $this->ledger->earn($account, $points, $eventKey, ['meta' => $meta]);
        }

        return $this->ledger->adjust($account, $points, $eventKey, ['meta' => $meta]);
    }

    /**
     * Claw back earned points when a previously completed order is cancelled.
     */
    public function reverseEarnForCancelledOrder(Order $order): void
    {
        if (! $this->isEnabled() || ! $order->customer_id) {
            return;
        }

        $earnTransaction = $this->ledger->findByEventKey(LoyaltyEventKey::orderEarn($order->id));

        if (! $earnTransaction || $earnTransaction->points <= 0) {
            return;
        }

        $account = $this->accountManager->firstOrCreateForCustomer($order->customer);

        $this->ledger->adjust(
            $account,
            -$earnTransaction->points,
            LoyaltyEventKey::orderCancelEarn($order->id),
            [
                'reference' => $order,
                'earn_transaction' => $earnTransaction,
                'meta' => ['reason' => 'order_cancelled'],
            ]
        );
    }

    /**
     * Estimate points that would be earned for a completed order (read-only).
     */
    public function estimateOrderPoints(Order $order): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        return $this->estimatePointsFromTotalMinor($this->toMinorAmount($order->total));
    }

    /**
     * Estimate points that would be earned from the current cart total (read-only).
     */
    public function estimateCartPoints(CartContract|Cart $cart): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        /** @var Cart $cart */
        if ($cart->total === null) {
            $cart = $cart->calculate();
        }

        return $this->estimatePointsFromTotalMinor($this->toMinorAmount($cart->total));
    }

    /**
     * Determine whether loyalty is enabled.
     */
    protected function isEnabled(): bool
    {
        return (bool) config('lunar.loyalty.enabled', true);
    }

    /**
     * Estimate earn points from an order total in minor units.
     */
    protected function estimatePointsFromTotalMinor(int $orderTotalMinor): int
    {
        $config = config('lunar.loyalty.events.order_completed', []);

        if (empty($config['calculator'])) {
            return 0;
        }

        $calculator = app($config['calculator']);
        $multiplier = (int) ($config['multiplier'] ?? 1);

        $basePoints = $calculator->calculate([
            'order_total_minor' => $orderTotalMinor,
        ]);

        return max(0, (int) floor($basePoints * $multiplier));
    }

    /**
     * Normalize a price or integer value to minor units.
     */
    protected function toMinorAmount(mixed $amount): int
    {
        if ($amount instanceof Price) {
            return (int) $amount->value;
        }

        return (int) $amount;
    }
}
