<?php

namespace Lunar\Addons\Shipping\Console;

use Illuminate\Console\Command;
use Lunar\Addons\Shipping\Models\ShippingCounty;
use Lunar\Addons\Shipping\Providers\Sameday\SamedayApiClient;

class SyncShippingCountiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    public $signature = 'lunar:sync-shipping-counties';

    /**
     * The console command description.
     *
     * @var string
     */
    public $description = 'Sync counties from shipping providers for locker shipping';

    public function handle(): int
    {
        if (! config('lunar.shipping.locker_enabled')) {
            $this->info('Locker shipping is not enabled, skipping sync.');

            return self::SUCCESS;
        }

        $this->info('Starting county sync...');

        // === SAMEDAY ===
        try {
            $this->info('Syncing Sameday counties...');
            $this->syncSameday();
            $this->info('Sameday counties synced successfully.');
        } catch (\Throwable $e) {
            $this->error("Sameday county-sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Shipping county sync complete.');

        return self::SUCCESS;
    }

    /**
     * Sync counties from Sameday shipping provider.
     *
     * @throws \Exception
     */
    protected function syncSameday(): void
    {
        if (! config('lunar.shipping.sameday.enabled')) {
            $this->info('Sameday shipping is not enabled, skipping sync.');

            return;
        }

        try {
            $response = app(SamedayApiClient::class)->getCounties();
            $data = $response['data'] ?? [];

            if (! is_array($data)) {
                $this->warn('Unexpected response format');
                throw new \Exception('Invalid data format received from Sameday API');
            }

            $syncedIds = [];

            foreach ($data as $county) {
                $syncedIds[] = $county['id'];

                ShippingCounty::updateOrCreate(
                    [
                        'provider' => 'sameday',
                        'provider_county_id' => $county['id'],
                    ],
                    [
                        'name' => $county['name'],
                        'code' => $county['code'],
                    ]
                );
            }

            // Soft-delete stale counties
            ShippingCounty::where('provider', 'sameday')
                ->whereNotIn('provider_county_id', $syncedIds)
                ->delete();
        } catch (\Throwable $e) {
            throw new \Exception("Failed to sync Sameday counties: {$e->getMessage()}");
        }
    }
}
