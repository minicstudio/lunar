<?php

namespace Lunar\Klaviyo\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Product;

class SyncProductsBulkToKlaviyo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    /**
     * @param  list<int>  $productIds
     */
    public function __construct(
        public array $productIds,
    ) {
        $this->tries = config('lunar.klaviyo.retry.max_attempts', 4);
        $this->backoff = config('lunar.klaviyo.retry.backoff', [60, 300, 3600]);
    }

    public function handle(): void
    {
        if (! KlaviyoAvailability::catalogSyncEnabled()) {
            KlaviyoLogger::warning('Bulk product sync job skipped — enabled or sync_products off', [
                'product_ids' => $this->productIds,
            ]);

            return;
        }

        if ($this->productIds === []) {
            return;
        }

        KlaviyoLogger::info('Bulk product sync job started', [
            'product_count' => count($this->productIds),
            'attempt' => $this->attempts(),
        ]);

        $products = Product::query()
            ->with(['variants', 'collections', 'brand', 'media'])
            ->whereIn('id', $this->productIds)
            ->get();

        try {
            app(KlaviyoCatalogService::class)->syncProductsBulk($products);

            KlaviyoLogger::info('Bulk product sync job completed', [
                'product_count' => $products->count(),
            ]);
        } catch (Exception $e) {
            KlaviyoLogger::error('Bulk product sync job failed', [
                'product_ids' => $this->productIds,
                'attempt' => $this->attempts(),
            ], $e);

            throw new FailedKlaviyoSyncException(
                'Klaviyo bulk product sync error. '.$e->getMessage()
            );
        }
    }
}
