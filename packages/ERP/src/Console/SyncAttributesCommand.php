<?php

namespace Lunar\ERP\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Services\ErpService;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;

class SyncAttributesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'erp:sync-attributes';

    /**
     * The console command description.
     */
    protected $description = 'Sync attributes from ERP system';

    /**
     * Execute the console command.
     */
    public function handle(ErpService $erpService): int
    {
        if (! config('lunar.erp.enabled')) {
            $this->error('ERP sync is disabled in configuration.');

            return Command::FAILURE;
        }

        $this->info('Starting attribute sync from ERP...');

        $enabledProviders = $erpService->getAllowedProviders('sync', 'attributes');

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
            // Get attributes from ERP
            $this->info('Fetching attributes from ERP...');
            $attributes = $erpService->getAttributes(ErpProviderEnum::from($selectedProvider));

            if (empty($attributes)) {
                $this->warn('No attributes found in ERP response.');

                return Command::SUCCESS;
            }

            $this->info('Found '.count($attributes).' attributes to process.');

            // Process and save attributes
            $result = $this->processAttributes($attributes);

            $this->info('Attribute sync completed successfully!');
            $this->info("Created {$result['attributes']} new attributes as product options.");

        } catch (\Exception $e) {
            $this->error('Failed to sync attributes: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Process and save attributes to the database as product options.
     */
    private function processAttributes(array $attributes): array
    {
        $attributeCount = 0;

        // Start progress bar
        $this->output->progressStart(count($attributes));

        DB::beginTransaction();

        try {
            foreach ($attributes as $attribute) {
                // update or create attribute
                $productOption = ProductOption::updateOrCreate([
                    'handle' => Str::slug($attribute['optionName']),
                ],
                    [
                        'name' => [
                            'ro' => $attribute['optionName'],
                        ],
                        'label' => [
                            'ro' => $attribute['optionName'],
                        ],
                        'shared' => 1,
                        'handle' => Str::slug($attribute['optionName']),
                    ]);

                // loop through option values
                foreach ($attribute['optionValues'] as $value) {
                    ProductOptionValue::updateOrCreate(
                        [
                            'product_option_id' => $productOption->id,
                            'name->ro' => $value,
                        ],
                        [
                            'position' => 0,
                        ]
                    );
                }

                if ($productOption->wasRecentlyCreated) {
                    $attributeCount++;
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
            'attributes' => $attributeCount,
        ];
    }
}
