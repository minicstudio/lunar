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
    protected $description = 'Queue a catalog backfill that fans out bulk Klaviyo sync jobs for available published products';

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

        $this->info('Dispatching product catalog sync coordinator to the queue...');

        SyncAllProductsToKlaviyo::dispatch($chunkSize);

        $this->newLine();
        $this->info('✓ Catalog sync coordinator dispatched successfully.');
        $this->info('It will queue bulk chunks (≤'.$chunkSize.' products) on the default queue; each chunk retries on Klaviyo 503s.');

        return self::SUCCESS;
    }
}
