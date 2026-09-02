<?php

namespace Lunar\Loyalty\Services;

use Illuminate\Support\Collection;
use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Models\LoyaltyTransaction;

final class LoyaltyExpirationService
{
    public function __construct(
        protected LoyaltyEngine $engine,
    ) {}

    /**
     * Find earn lots that have expired and still have remaining points.
     *
     * @return Collection<int, LoyaltyTransaction>
     */
    public function findExpiredLots(): Collection
    {
        return LoyaltyTransaction::query()
            ->where('type', LoyaltyTransactionType::Earn->value)
            ->where('remaining_points', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Find earn lots expiring within the given number of days.
     *
     * @param  int  $afterDays  Exclusive lower bound in days from now (0 = include everything from now).
     *                          Use this to create non-overlapping notification windows, e.g. afterDays=7
     *                          with days=30 returns only lots expiring strictly after 7 days and within 30.
     * @return Collection<int, LoyaltyTransaction>
     */
    public function findLotsExpiringWithinDays(int $days, int $afterDays = 0): Collection
    {
        $from = $afterDays > 0 ? now()->addDays($afterDays) : now();

        return LoyaltyTransaction::query()
            ->where('type', LoyaltyTransactionType::Earn->value)
            ->where('remaining_points', '>', 0)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$from, now()->addDays($days)])
            ->orderBy('expires_at')
            ->get();
    }

    /**
     * Expire all expired earn lots.
     */
    public function expireAll(): int
    {
        $count = 0;

        foreach ($this->findExpiredLots() as $lot) {
            if ($this->engine->expireLot($lot)) {
                $count++;
            }
        }

        return $count;
    }
}
