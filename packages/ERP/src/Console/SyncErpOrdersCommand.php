<?php

namespace Lunar\ERP\Console;

use Illuminate\Console\Command;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;

class SyncErpOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    public $signature = 'erp:sync-order-statuses';

    /**
     * The console command description.
     */
    public $description = 'Sync order statuses from ERP systems';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('lunar.erp.enabled')) {
            $this->info('ERP sync is not enabled, skipping sync.');

            return self::SUCCESS;
        }

        $this->info('Starting ERP order sync...');

        $erpService = app(ErpService::class);
        $enabledProviders = $erpService->getAllowedProviders('sync', 'orders');

        if (empty($enabledProviders)) {
            $this->info('No ERP providers are enabled.');

            return self::SUCCESS;
        }

        // Prepare provider choices
        $providerChoices = [];
        foreach ($enabledProviders as $provider) {
            $providerChoices[$provider->value] = ucfirst($provider->value);
        }

        // Ask user to select a provider
        $selectedProvider = $this->choice(
            'Which ERP provider would you like to sync order statuses from?',
            $providerChoices,
            array_key_first($providerChoices)
        );

        try {
            $provider = ErpProviderEnum::from($selectedProvider);
            $this->syncProvider($erpService, $provider);

            return self::SUCCESS;
        } catch (\ValueError $e) {
            $this->error("Invalid ERP provider: {$selectedProvider}");

            return self::FAILURE;
        }

        $this->info('ERP order sync completed.');

        return self::SUCCESS;
    }

    /**
     * Sync orders for a specific provider.
     */
    protected function syncProvider(ErpService $erpService, ErpProviderEnum $provider): void
    {
        try {
            $this->info("Syncing orders to {$provider->value}...");

            $result = $erpService->syncOrderStatuses($provider);

            if ($result['success']) {
                $this->info("✓ {$provider->value}: {$result['orders_processed']} orders synced successfully");
            } else {
                $message = "⚠ {$provider->value}: Sync completed with warnings".(! empty($result['message']) ? " - {$result['message']}" : '');

                $this->warn($message);
            }

        } catch (\Throwable $e) {
            $this->error("✗ {$provider->value}: Order sync failed - {$e->getMessage()}");
        }
    }
}
