<?php

namespace Lunar\Klaviyo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Enums\ProductEventType;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Product;

class SyncAllProductsToKlaviyo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $chunkSize = 100,
    ) {}

    public function handle(): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            KlaviyoLogger::warning('Sync all products job skipped — enabled or sync_products off', [
                'enabled' => (bool) config('lunar.klaviyo.enabled', false),
                'sync_products' => (bool) config('lunar.klaviyo.sync_products', false),
            ]);

            return;
        }

        KlaviyoLogger::info('Sync all products job started', [
            'chunk_size' => $this->chunkSize,
        ]);

        $dispatched = 0;
        $skippedUnavailable = 0;
        $scanned = 0;

        Product::query()
            ->with(['variants', 'collections', 'brand', 'media'])
            ->where('status', 'published')
            ->whereHas('variants', function ($variantQuery) {
                $variantQuery->where(function ($stockQuery) {
                    $stockQuery->where('stock', '>', 0)
                        ->orWhere('backorder', true);
                });
            })
            ->chunk($this->chunkSize, function ($products) use (&$dispatched, &$skippedUnavailable, &$scanned) {
                foreach ($products as $product) {
                    $scanned++;

                    if ($product->isAvailable()) {
                        SyncProductToKlaviyo::dispatch($product, ProductEventType::UPDATE);
                        $dispatched++;
                    } else {
                        $skippedUnavailable++;
                        KlaviyoLogger::info('Sync all products skipped product (not available)', [
                            'product_id' => $product->id,
                            'status' => $product->status,
                        ]);
                    }
                }
            });

        KlaviyoLogger::info('Sync all products job finished dispatching', [
            'scanned_published_with_stock' => $scanned,
            'dispatched' => $dispatched,
            'skipped_unavailable' => $skippedUnavailable,
        ]);
    }
}
