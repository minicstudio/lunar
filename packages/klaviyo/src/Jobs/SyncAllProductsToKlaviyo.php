<?php

namespace Lunar\Klaviyo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Facades\StorefrontSession;
use Lunar\Klaviyo\Support\KlaviyoAvailability;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Channel;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Product;

class SyncAllProductsToKlaviyo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    /**
     * Coordinator only scans and dispatches — keep well under typical worker timeouts.
     */
    public int $timeout = 120;

    /**
     * Seconds between bulk chunk dispatches to ease Klaviyo 429/503 pressure.
     */
    public int $chunkDelaySeconds = 15;

    public function __construct(
        public int $chunkSize = 100,
    ) {
        $this->tries = config('lunar.klaviyo.retry.max_attempts', 4);
        $this->backoff = config('lunar.klaviyo.retry.backoff', [60, 300, 3600]);
    }

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

        $availableIds = [];
        $skippedUnavailable = 0;
        $scanned = 0;

        Product::query()
            ->where('status', 'published')
            ->whereHas('variants', function ($variantQuery) {
                $variantQuery->where(function ($stockQuery) {
                    $stockQuery->where('stock', '>', 0)
                        ->orWhere('backorder', true);
                });
            })
            ->chunkById($chunkSize, function ($products) use (&$availableIds, &$skippedUnavailable, &$scanned) {
                foreach ($products as $product) {
                    $scanned++;

                    if ($product->isAvailable()) {
                        $availableIds[] = $product->id;

                        continue;
                    }

                    $skippedUnavailable++;
                    KlaviyoLogger::info('Sync all products skipped product (not available)', [
                        'product_id' => $product->id,
                        'status' => $product->status,
                    ]);
                }
            });

        $bulkChunksSubmitted = 0;

        foreach (array_chunk($availableIds, $chunkSize) as $index => $chunk) {
            $pending = SyncProductsBulkToKlaviyo::dispatch($chunk);

            if ($index > 0 && $this->chunkDelaySeconds > 0) {
                $pending->delay(now()->addSeconds($index * $this->chunkDelaySeconds));
            }

            $bulkChunksSubmitted++;
        }

        KlaviyoLogger::info('Sync all products job finished', [
            'scanned_published_with_stock' => $scanned,
            'bulk_chunks_submitted' => $bulkChunksSubmitted,
            'available_product_count' => count($availableIds),
            'skipped_unavailable' => $skippedUnavailable,
        ]);
    }
}
