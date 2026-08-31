<?php

namespace Lunar\Klaviyo\Commands;

use Illuminate\Console\Command;
use Lunar\Klaviyo\Jobs\SyncAllProductsToKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoAvailability;

class SyncAllProductsToKlaviyoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'klaviyo:sync-all-products
                            {--chunk=100 : Number of products to process at a time}';

    /**
     * @var string
     */
    protected $description = 'Dispatch a background job to sync all available published products to Klaviyo Catalogs API';

    public function handle(): int
    {
        if (! KlaviyoAvailability::enabled()) {
            $this->error('Klaviyo integration is not enabled. Set KLAVIYO_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        if (! KlaviyoAvailability::syncProducts()) {
            $this->error('Product catalog sync is not enabled. Set KLAVIYO_SYNC_PRODUCTS=true in your .env file.');

            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');

        $this->info('Dispatching product catalog sync job to Klaviyo...');

        SyncAllProductsToKlaviyo::dispatch($chunkSize);

        $this->newLine();
        $this->info('✓ Product catalog sync job dispatched successfully.');
        $this->info('The job will process available published products in the background.');

        return self::SUCCESS;
    }
}
