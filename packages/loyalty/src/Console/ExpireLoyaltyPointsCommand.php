<?php

namespace Lunar\Loyalty\Console;

use Illuminate\Console\Command;
use Lunar\Loyalty\Services\LoyaltyExpirationService;

class ExpireLoyaltyPointsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'loyalty:expire-points';

    /**
     * @var string
     */
    protected $description = 'Expire loyalty points for earn lots past their expiration date';

    /**
     * Execute the console command.
     */
    public function handle(LoyaltyExpirationService $expirationService): int
    {
        $count = $expirationService->expireAll();

        $this->info("Expired {$count} loyalty lot(s).");

        return self::SUCCESS;
    }
}
