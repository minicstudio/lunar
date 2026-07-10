<?php

namespace Lunar\Loyalty\Services;

use Illuminate\Database\Eloquent\Model;
use Lunar\Facades\DB;
use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Exceptions\InsufficientLoyaltyPointsException;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Models\LoyaltyTransaction;

final class LoyaltyLedger
{
    /**
     * Record an earn transaction.
     *
     * @param  array{reference?: Model, meta?: array<string, mixed>, expires_at?: \Illuminate\Support\Carbon|null}  $options
     */
    public function earn(LoyaltyAccount $account, int $points, string $eventKey, array $options = []): ?LoyaltyTransaction
    {
        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($account, $points, $eventKey, $options) {
            $account = LoyaltyAccount::query()->lockForUpdate()->findOrFail($account->id);

            if ($existing = $this->findByEventKey($eventKey)) {
                return $existing;
            }

            $expiresAt = $options['expires_at'] ?? $this->resolveExpirationDate();

            $transaction = LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'type' => LoyaltyTransactionType::Earn,
                'points' => $points,
                'remaining_points' => $points,
                'event_key' => $eventKey,
                'reference_type' => isset($options['reference']) ? $options['reference']->getMorphClass() : null,
                'reference_id' => isset($options['reference']) ? $options['reference']->id : null,
                'meta' => array_merge(['notifications' => []], $options['meta'] ?? []),
                'expires_at' => $expiresAt,
            ]);

            $account->increment('balance', $points);
            $account->increment('lifetime_earned', $points);

            return $transaction;
        });
    }

    /**
     * Record a spend transaction with FIFO lot allocation.
     *
     * @param  array{reference?: Model, meta?: array<string, mixed>}  $options
     */
    public function spend(LoyaltyAccount $account, int $points, string $eventKey, array $options = []): ?LoyaltyTransaction
    {
        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($account, $points, $eventKey, $options) {
            $account = LoyaltyAccount::query()->lockForUpdate()->findOrFail($account->id);

            if ($existing = $this->findByEventKey($eventKey)) {
                return $existing;
            }

            $available = $this->aggregateAvailableFromLots($account);

            if ($points > $available) {
                throw InsufficientLoyaltyPointsException::forRequested($points, $available);
            }

            $allocations = $this->allocateFifo($account, $points);

            $transaction = LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'type' => LoyaltyTransactionType::Spend,
                'points' => $points,
                'remaining_points' => null,
                'event_key' => $eventKey,
                'reference_type' => isset($options['reference']) ? $options['reference']->getMorphClass() : null,
                'reference_id' => isset($options['reference']) ? $options['reference']->id : null,
                'meta' => array_merge(['allocations' => $allocations], $options['meta'] ?? []),
                'expires_at' => null,
            ]);

            $account->decrement('balance', $points);
            $account->increment('lifetime_spent', $points);

            return $transaction;
        });
    }

    /**
     * Record an expire transaction for a single earn lot.
     */
    public function expire(LoyaltyTransaction $earnLot, string $eventKey): ?LoyaltyTransaction
    {
        return DB::transaction(function () use ($earnLot, $eventKey) {
            $earnLot = LoyaltyTransaction::query()->lockForUpdate()->findOrFail($earnLot->id);
            $account = LoyaltyAccount::query()->lockForUpdate()->findOrFail($earnLot->loyalty_account_id);

            if ($existing = $this->findByEventKey($eventKey)) {
                return $existing;
            }

            $points = (int) $earnLot->remaining_points;

            if ($points <= 0) {
                return null;
            }

            $transaction = LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'type' => LoyaltyTransactionType::Expire,
                'points' => $points,
                'remaining_points' => null,
                'event_key' => $eventKey,
                'reference_type' => $earnLot->getMorphClass(),
                'reference_id' => $earnLot->id,
                'meta' => ['earn_transaction_id' => $earnLot->id],
                'expires_at' => null,
            ]);

            $earnLot->update(['remaining_points' => 0]);
            $account->decrement('balance', $points);

            return $transaction;
        });
    }

    /**
     * Record an adjust transaction with signed points.
     *
     * @param  array{reference?: Model, meta?: array<string, mixed>, spend_transaction?: LoyaltyTransaction, earn_transaction?: LoyaltyTransaction}  $options
     */
    public function adjust(LoyaltyAccount $account, int $points, string $eventKey, array $options = []): ?LoyaltyTransaction
    {
        if ($points === 0) {
            return null;
        }

        return DB::transaction(function () use ($account, $points, $eventKey, $options) {
            $account = LoyaltyAccount::query()->lockForUpdate()->findOrFail($account->id);

            if ($existing = $this->findByEventKey($eventKey)) {
                return $existing;
            }

            if ($points > 0 && isset($options['spend_transaction'])) {
                $this->restoreAllocations($options['spend_transaction'], $points);
                $account->decrement('lifetime_spent', min($points, (int) $account->lifetime_spent));
            }

            if ($points < 0) {
                if (isset($options['earn_transaction'])) {
                    $this->decrementEarnLot($options['earn_transaction'], abs($points));
                    $account->decrement('lifetime_earned', min(abs($points), (int) $account->lifetime_earned));
                } else {
                    $available = $this->aggregateAvailableFromLots($account);

                    if (abs($points) > $available) {
                        throw InsufficientLoyaltyPointsException::forRequested(abs($points), $available);
                    }

                    $allocations = $this->allocateFifo($account, abs($points));
                    $options['meta'] = array_merge($options['meta'] ?? [], ['allocations' => $allocations]);
                }
            }

            $transaction = LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'type' => LoyaltyTransactionType::Adjust,
                'points' => $points,
                'remaining_points' => null,
                'event_key' => $eventKey,
                'reference_type' => isset($options['reference']) ? $options['reference']->getMorphClass() : null,
                'reference_id' => isset($options['reference']) ? $options['reference']->id : null,
                'meta' => $options['meta'] ?? [],
                'expires_at' => null,
            ]);

            $account->increment('balance', $points);

            return $transaction;
        });
    }

    /**
     * Rebuild balance from ledger transactions.
     */
    public function aggregateBalanceFromLedger(LoyaltyAccount $account): int
    {
        $earned = (int) $account->transactions()
            ->where('type', LoyaltyTransactionType::Earn->value)
            ->sum('points');

        $spent = (int) $account->transactions()
            ->where('type', LoyaltyTransactionType::Spend->value)
            ->sum('points');

        $expired = (int) $account->transactions()
            ->where('type', LoyaltyTransactionType::Expire->value)
            ->sum('points');

        $adjusted = (int) $account->transactions()
            ->where('type', LoyaltyTransactionType::Adjust->value)
            ->sum('points');

        return $earned - $spent - $expired + $adjusted;
    }

    /**
     * Rebuild lifetime earned from ledger transactions (net of clawbacks).
     */
    public function aggregateLifetimeEarnedFromLedger(LoyaltyAccount $account): int
    {
        $earned = (int) $account->transactions()
            ->where('type', LoyaltyTransactionType::Earn->value)
            ->sum('points');

        $clawedBack = (int) $account->transactions()
            ->where('type', LoyaltyTransactionType::Adjust->value)
            ->where('points', '<', 0)
            ->where(function ($query) {
                $query->where('event_key', 'like', 'order:%:cancel:earn')
                    ->orWhere('event_key', 'like', 'order:%:refund:%');
            })
            ->sum('points');

        return max(0, $earned + $clawedBack);
    }

    /**
     * Rebuild lifetime spent from ledger transactions (net of spend reversals).
     */
    public function aggregateLifetimeSpentFromLedger(LoyaltyAccount $account): int
    {
        $spent = (int) $account->transactions()
            ->where('type', LoyaltyTransactionType::Spend->value)
            ->sum('points');

        $reversed = (int) $account->transactions()
            ->where('type', LoyaltyTransactionType::Adjust->value)
            ->where('points', '>', 0)
            ->where('event_key', 'like', 'order:%:cancel:spend')
            ->sum('points');

        return max(0, $spent - $reversed);
    }

    /**
     * Sum remaining points on non-expired earn lots.
     */
    public function aggregateAvailableFromLots(LoyaltyAccount $account): int
    {
        return $account->getAvailableBalance();
    }

    /**
     * Check whether cached balance matches ledger aggregation.
     */
    public function assertBalanceConsistent(LoyaltyAccount $account): bool
    {
        return $account->balance === $this->aggregateBalanceFromLedger($account);
    }

    /**
     * Check whether cached balance matches lot sum.
     */
    public function assertLotsConsistent(LoyaltyAccount $account): bool
    {
        return $account->balance === $this->aggregateAvailableFromLots($account);
    }

    /**
     * Find an existing transaction by event key.
     */
    public function findByEventKey(string $eventKey): ?LoyaltyTransaction
    {
        return LoyaltyTransaction::query()->where('event_key', $eventKey)->first();
    }

    /**
     * Allocate points from earn lots using FIFO ordering.
     *
     * @return array<int, array{earn_transaction_id: int, points: int}>
     */
    protected function allocateFifo(LoyaltyAccount $account, int $points): array
    {
        $remaining = $points;
        $allocations = [];

        $lots = LoyaltyTransaction::query()
            ->where('loyalty_account_id', $account->id)
            ->where('type', LoyaltyTransactionType::Earn->value)
            ->where('remaining_points', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, (int) $lot->remaining_points);
            $lot->decrement('remaining_points', $take);
            $allocations[] = [
                'earn_transaction_id' => $lot->id,
                'points' => $take,
            ];
            $remaining -= $take;
        }

        return $allocations;
    }

    /**
     * Restore remaining points on lots debited by a spend transaction.
     */
    protected function restoreAllocations(LoyaltyTransaction $spendTransaction, int $pointsToRestore): void
    {
        $allocations = $spendTransaction->meta['allocations'] ?? [];
        $remaining = $pointsToRestore;

        foreach (array_reverse($allocations) as $allocation) {
            if ($remaining <= 0) {
                break;
            }

            $restore = min($remaining, (int) $allocation['points']);
            $lot = LoyaltyTransaction::query()->lockForUpdate()->find($allocation['earn_transaction_id']);

            if ($lot) {
                $lot->increment('remaining_points', $restore);
            }

            $remaining -= $restore;
        }
    }

    /**
     * Decrement remaining points on an earn lot for clawback.
     */
    protected function decrementEarnLot(LoyaltyTransaction $earnTransaction, int $points): void
    {
        $lot = LoyaltyTransaction::query()->lockForUpdate()->findOrFail($earnTransaction->id);
        $decrement = min($points, (int) $lot->remaining_points);
        $lot->decrement('remaining_points', $decrement);
    }

    /**
     * Resolve the expiration date for new earn transactions.
     */
    protected function resolveExpirationDate(): ?\Illuminate\Support\Carbon
    {
        $months = (int) config('lunar.loyalty.expiration.months', 0);

        if ($months <= 0) {
            return null;
        }

        return now()->addMonths($months);
    }
}
