<?php

namespace Lunar\ERP\Console;

use Illuminate\Console\Command;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;

class SyncErpProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    public $signature = 'erp:sync-products';

    /**
     * The console command description.
     */
    public $description = 'Sync products from ERP systems';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('lunar.erp.enabled')) {
            $this->info('ERP sync is not enabled, skipping sync.');

            return self::SUCCESS;
        }

        $this->info('Starting ERP product sync...');

        $erpService = app(ErpService::class);
        $enabledProviders = $erpService->getAllowedProviders('sync', 'products');

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
            'Which ERP provider would you like to sync?',
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

        $this->info('ERP product sync completed.');

        return self::SUCCESS;
    }

    /**
     * Sync products for a specific provider.
     */
    protected function syncProvider(ErpService $erpService, ErpProviderEnum $provider): void
    {
        try {
            $this->info("Syncing products from {$provider->value}. Wait for progress...");

            // Create a simple progress callback
            $progressCallback = function (int $current, int $total, string $message = '') {
                static $progressBar = null;

                // Create progress bar on first call
                if ($progressBar === null && $total > 0) {
                    $progressBar = $this->output->createProgressBar($total);
                    $progressBar->setFormat('%current%/%max% [%bar%] %percent:3s%% %message%');
                    $progressBar->start();
                }

                if ($progressBar) {
                    $progressBar->setMessage($message);
                    $progressBar->setProgress($current);

                    // Finish when complete
                    if ($current >= $total) {
                        $progressBar->finish();
                        $this->newLine();
                    }
                }
            };

            $result = $erpService->syncProducts($provider, $progressCallback);

            if ($result['success']) {
                $this->info("✓ {$provider->value}: {$result['products_processed']} products synced successfully");
            } else {
                $message = "⚠ {$provider->value}: Sync completed with warnings".(! empty($result['message']) ? " - {$result['message']}" : '');

                $this->warn($message);
            }

        } catch (\Throwable $e) {
            $this->error("✗ {$provider->value}: Product sync failed - {$e->getMessage()}");
        }
    }
}
