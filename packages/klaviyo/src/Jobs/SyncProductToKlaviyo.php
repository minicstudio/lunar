<?php

namespace Lunar\Klaviyo\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Enums\ProductEventType;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Product;

class SyncProductToKlaviyo implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    public function __construct(
        public Product $product,
        public ProductEventType $eventType = ProductEventType::UPDATE,
    ) {
        $this->tries = config('lunar.klaviyo.retry.max_attempts', 4);
        $this->backoff = config('lunar.klaviyo.retry.backoff', [60, 300, 3600]);
    }

    public function uniqueId(): string
    {
        return 'klaviyo-product-sync-'.$this->product->id;
    }

    public function handle(): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            KlaviyoLogger::warning('Product sync job skipped — enabled or sync_products off', [
                'product_id' => $this->product->id,
                'enabled' => (bool) config('lunar.klaviyo.enabled', false),
                'sync_products' => (bool) config('lunar.klaviyo.sync_products', false),
            ]);

            return;
        }

        KlaviyoLogger::info('Product sync job started', [
            'product_id' => $this->product->id,
            'event_type' => $this->eventType->value,
            'attempt' => $this->attempts(),
            'product_status' => $this->product->status,
        ]);

        $catalogService = app(KlaviyoCatalogService::class);

        try {
            $result = match ($this->eventType) {
                ProductEventType::CREATE, ProductEventType::UPDATE => $catalogService->syncProduct($this->product),
                ProductEventType::DELETE => $catalogService->deleteProduct($this->product),
            };

            KlaviyoLogger::info('Product sync job completed', [
                'product_id' => $this->product->id,
                'event_type' => $this->eventType->value,
                'outcome' => $this->eventType === ProductEventType::DELETE
                    ? 'deleted'
                    : (empty($result) ? 'deleted_or_skipped_unavailable' : 'upserted'),
            ]);
        } catch (Exception $e) {
            KlaviyoLogger::error('Product sync job failed', [
                'product_id' => $this->product->id,
                'event_type' => $this->eventType->value,
                'attempt' => $this->attempts(),
            ], $e);

            throw new FailedKlaviyoSyncException(
                'Klaviyo product sync error for product '.$this->product->id.'. '.$e->getMessage()
            );
        }
    }
}
