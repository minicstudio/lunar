<?php

namespace Lunar\Loyalty\Console;

use Illuminate\Console\Command;
use Lunar\Facades\DB;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyLedger;

class RecalculateBalancesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'loyalty:recalculate-balances
                            {--account= : Scope to a specific loyalty account ID}
                            {--fix : Fix cached balance from ledger aggregation}';

    /**
     * @var string
     */
    protected $description = 'Audit loyalty account balances against ledger and lot sums';

    /**
     * Execute the console command.
     */
    public function handle(LoyaltyLedger $ledger): int
    {
        $query = LoyaltyAccount::query();

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $inconsistencies = 0;

        $query->chunkById(100, function ($accounts) use ($ledger, &$inconsistencies) {
            foreach ($accounts as $account) {
                $ledgerBalance = $ledger->aggregateBalanceFromLedger($account);
                $lotsBalance = $ledger->aggregateAvailableFromLots($account);
                $cached = $account->balance;

                $ledgerMismatch = $cached !== $ledgerBalance;
                $lotsMismatch = $cached !== $lotsBalance;
                $lifetimeEarned = $ledger->aggregateLifetimeEarnedFromLedger($account);
                $lifetimeSpent = $ledger->aggregateLifetimeSpentFromLedger($account);
                $lifetimeEarnedMismatch = (int) $account->lifetime_earned !== $lifetimeEarned;
                $lifetimeSpentMismatch = (int) $account->lifetime_spent !== $lifetimeSpent;

                if (! $ledgerMismatch && ! $lotsMismatch && ! $lifetimeEarnedMismatch && ! $lifetimeSpentMismatch) {
                    continue;
                }

                $inconsistencies++;

                $this->warn("Account #{$account->id}: cached={$cached}, ledger={$ledgerBalance}, lots={$lotsBalance}");

                if ($lifetimeEarnedMismatch || $lifetimeSpentMismatch) {
                    $this->warn("  Lifetime counters: earned={$account->lifetime_earned} (expected {$lifetimeEarned}), spent={$account->lifetime_spent} (expected {$lifetimeSpent})");
                }

                if ($this->option('fix') && ($ledgerMismatch || $lifetimeEarnedMismatch || $lifetimeSpentMismatch)) {
                    DB::transaction(function () use ($account, $ledgerBalance, $lifetimeEarned, $lifetimeSpent, $ledgerMismatch) {
                        $updates = [];

                        if ($ledgerMismatch) {
                            $updates['balance'] = $ledgerBalance;
                        }

                        $updates['lifetime_earned'] = $lifetimeEarned;
                        $updates['lifetime_spent'] = $lifetimeSpent;

                        LoyaltyAccount::query()
                            ->where('id', $account->id)
                            ->lockForUpdate()
                            ->update($updates);
                    });

                    if ($ledgerMismatch) {
                        $this->info("  Fixed cached balance to {$ledgerBalance}.");
                    }

                    if ($lifetimeEarnedMismatch || $lifetimeSpentMismatch) {
                        $this->info("  Fixed lifetime counters to earned={$lifetimeEarned}, spent={$lifetimeSpent}.");
                    }
                }

                if ($lotsMismatch) {
                    $this->warn('  Lot drift detected — manual reconciliation required.');
                }
            }
        });

        if ($inconsistencies === 0) {
            $this->info('All loyalty account balances are consistent.');

            return self::SUCCESS;
        }

        $this->error("Found {$inconsistencies} inconsistent account(s).");

        return $this->option('fix') ? self::SUCCESS : self::FAILURE;
    }
}
