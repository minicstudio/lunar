<?php

namespace Lunar\Addons\Shipping\Console;

use Illuminate\Console\Command;
use Lunar\Addons\Shipping\Models\ShippingCity;
use Lunar\Addons\Shipping\Models\ShippingCounty;
use Lunar\Addons\Shipping\Models\ShippingLocker;
use Lunar\Addons\Shipping\Providers\Sameday\SamedayApiClient;

class SyncShippingLockersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    public $signature = 'lunar:sync-shipping-lockers';

    /**
     * The console command description.
     *
     * @var string
     */
    public $description = 'Sync shipping lockers from supported providers';

    public function handle(): int
    {
        if (! config('lunar.shipping.locker_enabled')) {
            $this->info('Locker shipping is not enabled, skipping sync.');

            return self::SUCCESS;
        }

        $this->info('Starting locker sync...');

        // === SAMEDAY ===
        try {
            $this->info('Syncing Sameday Lockers...');
            $this->syncSameday();
            $this->info('Sameday lockers synced successfully.');
        } catch (\Throwable $e) {
            $this->error("Sameday sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info('Locker sync complete.');

        return self::SUCCESS;
    }

    /**
     * Sync lockers from Sameday API.
     * This method handles pagination, upserts lockers, and soft-deletes any lockers that no longer exist in the remote provider.
     * NOTE: The locker creation and deletion bypasses model events and directly interacts with the database for performance reasons, as this is a bulk operation,
     * so the Laravel eloquent lifecycle is not triggered.
     */
    protected function syncSameday(): void
    {
        if (! config('lunar.shipping.sameday.enabled')) {
            $this->info('Sameday shipping is not enabled, skipping sync.');

            return;
        }

        $page = 1;
        $client = app(SamedayApiClient::class);
        $syncedIds = [];
        $completed = true;

        $countyMap = ShippingCounty::where('provider', 'sameday')
            ->pluck('id', 'provider_county_id')
            ->toArray();
        $cityMap = ShippingCity::where('provider', 'sameday')
            ->pluck('id', 'provider_city_id')
            ->toArray();

        $uniqueKeys = ['provider', 'provider_locker_id'];
        $updateFields = [
            'name',
            'locker_type',
            'county',
            'county_id',
            'provider_county_id',
            'city',
            'city_id',
            'provider_city_id',
            'postal_code',
            'address',
            'lat',
            'lng',
            'deleted_at',
            'updated_at',
        ];

        do {
            $response = $client->getLockerLocationsPaginated($page);
            $data = $response['data'] ?? [];
            $totalPages = max((int) ($response['pages'] ?? 1), 1);

            $this->line("Processing page {$page}/{$totalPages}...");

            if (! is_array($data)) {
                $this->warn("Unexpected response structure on page {$page}, skipping...");
                $completed = false;
                break;
            }

            $upsertData = [];

            foreach ($data as $locker) {
                $syncedIds[] = $locker['oohId'];
                $upsertData[] = [
                    'provider' => 'sameday',
                    'provider_locker_id' => $locker['oohId'],
                    'name' => $locker['name'],
                    'locker_type' => $locker['oohType'],
                    'county' => $locker['county'],
                    'county_id' => $countyMap[$locker['countyId']] ?? null,
                    'provider_county_id' => $locker['countyId'],
                    'city' => $locker['city'],
                    'city_id' => $cityMap[$locker['cityId']] ?? null,
                    'provider_city_id' => $locker['cityId'],
                    'postal_code' => $locker['postalCode'],
                    'address' => $locker['address'],
                    'lat' => $locker['lat'],
                    'lng' => $locker['lng'],
                    'deleted_at' => null, // restore if soft-deleted
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
            }

            if (! empty($upsertData)) {
                // Chunking the upsert itself (e.g., 200 at a time) to prevent DB parameter limits
                foreach (array_chunk($upsertData, 200) as $chunk) {
                    ShippingLocker::withTrashed()->upsert($chunk, $uniqueKeys, $updateFields);
                }
            }

            $page++;
        } while ($page <= $totalPages);

        if (! $completed) {
            $this->warn('Sync was not completed, skipping deletion to avoid data loss.');

            return;
        }

        // Clean up: Soft-delete lockers no longer present in the API
        ShippingLocker::where('provider', 'sameday')
            ->whereNotIn('provider_locker_id', $syncedIds)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);
    }
}
