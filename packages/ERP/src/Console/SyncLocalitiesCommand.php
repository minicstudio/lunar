<?php

namespace Lunar\ERP\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Models\County;
use Lunar\ERP\Models\Locality;
use Lunar\ERP\Services\ErpService;
use Lunar\Models\Country;

class SyncLocalitiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'erp:sync-localities';

    /**
     * The console command description.
     */
    protected $description = 'Sync localities (counties and cities) from ERP system';

    /**
     * Execute the console command.
     */
    public function handle(ErpService $erpService): int
    {
        if (! config('lunar.erp.enabled')) {
            $this->error('ERP sync is disabled in configuration.');

            return Command::FAILURE;
        }

        $this->info('Starting localities sync from ERP...');

        // Get Romania country
        $romania = Country::where('iso2', 'RO')->first();
        if (! $romania) {
            $this->error('Romania country not found. Please ensure countries are seeded.');

            return Command::FAILURE;
        }

        $enabledProviders = $erpService->getAllowedProviders('sync', 'localities');

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
            // Get localities from ERP
            $this->info('Fetching localities from ERP...');
            $localities = $erpService->getLocalities(ErpProviderEnum::from($selectedProvider));

            if (empty($localities)) {
                $this->warn('No localities found in ERP response.');

                return Command::SUCCESS;
            }

            $this->info('Found '.count($localities).' localities to process.');

            // Process and save localities
            $result = $this->processLocalities($localities, $romania);

            $this->info('Localities sync completed successfully!');
            $this->info("Created {$result['counties']} new counties and {$result['localities']} new localities.");

        } catch (\Exception $e) {
            $this->error('Failed to sync localities: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Process and save localities to the database.
     */
    private function processLocalities(array $localities, Country $romania): array
    {
        $countyCount = 0;
        $localityCount = 0;

        // Start progress bar
        $this->output->progressStart(count($localities));

        DB::beginTransaction();

        try {
            foreach ($localities as $locality) {
                $county = County::updateOrCreate(
                    [
                        'code' => $locality['countyCode'],
                        'country_id' => $romania->id,
                    ],
                    [
                        'name' => $locality['countyName'],
                    ]
                );

                if ($county->wasRecentlyCreated) {
                    $countyCount++;
                }

                $locality = Locality::updateOrCreate(
                    [
                        'name' => $locality['localityName'],
                        'county_id' => $county->id,
                    ]
                );

                if ($locality->wasRecentlyCreated) {
                    $localityCount++;
                }

                // Advance progress bar
                $this->output->progressAdvance();
            }

            DB::commit();

            // Finish progress bar
            $this->output->progressFinish();

        } catch (\Exception $e) {
            DB::rollBack();

            // Finish progress bar even on error
            $this->output->progressFinish();

            throw $e;
        }

        return [
            'counties' => $countyCount,
            'localities' => $localityCount,
        ];
    }
}
