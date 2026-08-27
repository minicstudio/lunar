<?php

namespace Lunar\Klaviyo\Commands;

use Illuminate\Console\Command;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;

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
    protected $description = 'Delete all catalog items (and their variants) from the Klaviyo Catalogs API';

    public function handle(KlaviyoCatalogService $catalogService): int
    {
        if (! config('lunar.klaviyo.enabled', false)) {
            $this->error('Klaviyo integration is not enabled. Set KLAVIYO_ENABLED=true in your .env file.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will permanently delete ALL products from the Klaviyo catalog. Continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $pageSize = max(1, min(100, (int) $this->option('page-size')));

        $this->info('Deleting all catalog items from Klaviyo...');

        try {
            $result = $catalogService->deleteAllCatalogItems($pageSize);
        } catch (FailedKlaviyoSyncException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("✓ Queued deletion of {$result['deleted']} catalog item(s) across {$result['jobs']} bulk delete job(s).");
        $this->comment('Klaviyo processes bulk deletes asynchronously; variants are removed with their parent items.');

        return self::SUCCESS;
    }
}
