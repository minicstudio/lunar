<?php

namespace Lunar\Klaviyo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Facades\StorefrontSession;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Channel;
use Lunar\Models\CustomerGroup;
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
        if (! KlaviyoAvailability::catalogSyncEnabled()) {
            KlaviyoLogger::warning('Sync all products job skipped — enabled or sync_products off', [
                'enabled' => KlaviyoAvailability::enabled(),
                'sync_products' => KlaviyoAvailability::syncProducts(),
            ]);

            return;
        }

        $chunkSize = max(1, min(100, $this->chunkSize));

        KlaviyoLogger::info('Sync all products job started', [
            'chunk_size' => $chunkSize,
        ]);

        $channel = Channel::getDefault();

        if ($channel) {
            StorefrontSession::setChannel($channel);
        }

        $customerGroup = CustomerGroup::getDefault();

        if ($customerGroup) {
            StorefrontSession::setCustomerGroups(collect([$customerGroup]));
        }

        $bulkChunksSubmitted = 0;
        $skippedUnavailable = 0;
        $scanned = 0;
        $catalogService = app(KlaviyoCatalogService::class);

        Product::query()
            ->with(['variants', 'collections', 'brand', 'media'])
            ->where('status', 'published')
            ->whereHas('variants', function ($variantQuery) {
                $variantQuery->where(function ($stockQuery) {
                    $stockQuery->where('stock', '>', 0)
                        ->orWhere('backorder', true);
                });
            })
            ->chunk($chunkSize, function ($products) use ($catalogService, &$bulkChunksSubmitted, &$skippedUnavailable, &$scanned) {
                $available = collect();

                foreach ($products as $product) {
                    $scanned++;

                    if ($product->isAvailable()) {
                        $available->push($product);
                    } else {
                        $skippedUnavailable++;
                        KlaviyoLogger::info('Sync all products skipped product (not available)', [
                            'product_id' => $product->id,
                            'status' => $product->status,
                        ]);
                    }
                }

                if ($available->isEmpty()) {
                    return;
                }

                $catalogService->syncProductsBulk($available);
                $bulkChunksSubmitted++;
            });

        KlaviyoLogger::info('Sync all products job finished', [
            'scanned_published_with_stock' => $scanned,
            'bulk_chunks_submitted' => $bulkChunksSubmitted,
            'skipped_unavailable' => $skippedUnavailable,
        ]);
    }
}
