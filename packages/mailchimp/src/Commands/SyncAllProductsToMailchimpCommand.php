<?php

namespace Lunar\Mailchimp\Commands;

use Illuminate\Console\Command;
use Lunar\Mailchimp\Jobs\SyncAllProductsToMailchimp;

class SyncAllProductsToMailchimpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailchimp:sync-all-products
                            {--chunk=100 : Number of products to process at a time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a background job to sync all available products to Mailchimp Ecommerce API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Check if Mailchimp is enabled
        if (! config('lunar.mailchimp.enabled', false)) {
            $this->error('Mailchimp integration is not enabled. Set MAILCHIMP_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        if (! config('lunar.mailchimp.sync_products', false)) {
            $this->error('Product sync is not enabled. Set MAILCHIMP_SYNC_PRODUCTS=true in your .env file.');

            return self::FAILURE;
        }

        $chunkSize = (int) $this->option('chunk');

        $this->info('Dispatching product sync job to Mailchimp...');

        SyncAllProductsToMailchimp::dispatch($chunkSize);

        $this->newLine();
        $this->info('✓ Product sync job dispatched successfully.');
        $this->info('The job will process all available products in the background using isAvailable() check.');

        return self::SUCCESS;
    }
}
