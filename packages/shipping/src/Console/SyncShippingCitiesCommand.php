<?php

namespace Lunar\Addons\Shipping\Console;

use Illuminate\Console\Command;
use Lunar\Addons\Shipping\Models\ShippingCity;
use Lunar\Addons\Shipping\Models\ShippingCounty;
use Lunar\Addons\Shipping\Providers\Sameday\SamedayApiClient;

class SyncShippingCitiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    public $signature = 'lunar:sync-shipping-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    public $description = 'Sync cities from shipping providers for locker shipping';

    public function handle(): int
    {
        if (! config('lunar.shipping.locker_enabled')) {
            $this->info('Locker shipping is not enabled, skipping sync.');

            return self::SUCCESS;
        }

        $this->info('Starting city sync...');

        // === SAMEDAY ===
        try {
            $this->info('Syncing Sameday cities...');
            $this->syncSameday();
            $this->info('Sameday cities synced successfully.');
        } catch (\Throwable $e) {
            $this->error("Sameday city-sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Shipping city sync complete.');

        return self::SUCCESS;
    }

    /**
     * Sync cities from Sameday shipping provider.
     *
     * @throws \Exception
     */
    protected function syncSameday(): void
    {
        if (! config('lunar.shipping.sameday.enabled')) {
            $this->info('Sameday shipping is not enabled, skipping sync.');

            return;
        }

        $client = app(SamedayApiClient::class);
        $counties = ShippingCounty::where('provider', 'sameday')->get();

        foreach ($counties as $county) {
            $this->info("Syncing cities for county: {$county->name} ({$county->provider_county_id})");

            $page = 1;
            $syncedIds = [];

            do {
                $response = $client->getCities($county->provider_county_id, $page);
                $data = $response['data'] ?? [];
                $totalPages = max((int) ($response['pages'] ?? 1), 1);

                $this->line("  Processing page {$page}/{$totalPages}...");

                if (! is_array($data)) {
                    $this->warn("  Unexpected response on page {$page} for county ID {$county->provider_county_id}, skipping...");
                    break;
                }

                foreach ($data as $city) {
                    $syncedIds[] = $city['id'];

                    ShippingCity::updateOrCreate(
                        [
                            'provider' => 'sameday',
                            'provider_city_id' => $city['id'],
                        ],
                        [
                            'name' => $city['name'],
                            'postal_code' => $city['postalCode'],
                            'county_id' => $county->id,
                            'provider_county_id' => $county->provider_county_id,
                        ]
                    );
                }

                $page++;
            } while ($page <= $totalPages);

            // mark stale cities for that county as deleted
            ShippingCity::where('provider', 'sameday')
                ->where('provider_county_id', $county->provider_county_id)
                ->whereNotIn('provider_city_id', $syncedIds)
                ->delete();
        }
    }
}
