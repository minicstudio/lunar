<?php

namespace Lunar\Klaviyo\Commands;

use Illuminate\Console\Command;
use Lunar\Klaviyo\Jobs\DeleteAllProductsFromKlaviyo;
use Lunar\Klaviyo\Support\KlaviyoAvailability;

class DeleteAllProductsFromKlaviyoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'klaviyo:delete-all-products
                            {--force : Skip the confirmation prompt}
                            {--page-size=100 : Catalog items fetched per API page (max 100)}';

    /**
     * @var string
     */
    protected $description = 'Dispatch a background job to delete all catalog items (and their variants) from the Klaviyo Catalogs API';

    public function handle(): int
    {
        if (! KlaviyoAvailability::enabled()) {
            $this->error('Klaviyo integration is not enabled. Set KLAVIYO_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will permanently delete ALL products from the Klaviyo catalog. Continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $pageSize = max(1, min(100, (int) $this->option('page-size')));

        $this->info('Dispatching catalog wipe job to Klaviyo...');

        DeleteAllProductsFromKlaviyo::dispatch($pageSize);

        $this->newLine();
        $this->info('✓ Catalog wipe job dispatched successfully.');
        $this->comment('The job will list remote catalog items and queue Klaviyo bulk-delete jobs; variants are removed with their parent items.');

        return self::SUCCESS;
    }
}
