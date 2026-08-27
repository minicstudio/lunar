<?php

namespace Lunar\Klaviyo\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Lunar\Enums\ProductEventType;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Support\CatalogExternalIdStore;
use Lunar\Klaviyo\Support\KlaviyoLogger;
use Lunar\Models\Product;

class SyncProductToKlaviyo implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries;

    public array $backoff;

    /**
     * @param  list<string>  $additionalExternalIds
     */
    public function __construct(
        public int $productId,
        public ProductEventType $eventType = ProductEventType::UPDATE,
        public ?string $itemExternalId = null,
        public array $additionalExternalIds = [],
    ) {
        $this->tries = config('lunar.klaviyo.retry.max_attempts', 4);
        $this->backoff = config('lunar.klaviyo.retry.backoff', [60, 300, 3600]);
    }

    /**
     * Build a job from a live product, capturing external ids for DELETE while variants exist.
     */
    public static function fromProduct(Product $product, ProductEventType $eventType = ProductEventType::UPDATE): self
    {
        $itemExternalId = null;
        $additionalExternalIds = [];

        if ($eventType === ProductEventType::DELETE) {
            $catalogService = app(KlaviyoCatalogService::class);
            $captured = $catalogService->captureExternalIdsForProduct($product);
            $itemExternalId = $captured[0] ?? (string) $product->id;
            $additionalExternalIds = array_values(array_filter(
                $captured,
                fn (string $id) => $id !== $itemExternalId
            ));
            CatalogExternalIdStore::rememberIfAbsent($product->id, $itemExternalId);
        }

        return new self(
            productId: $product->id,
            eventType: $eventType,
            itemExternalId: $itemExternalId,
            additionalExternalIds: $additionalExternalIds,
        );
    }

    public function uniqueId(): string
    {
        $base = 'klaviyo-product-sync-'.$this->productId;

        return $this->eventType === ProductEventType::DELETE
            ? $base.'-delete'
            : $base;
    }

    public function handle(): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            KlaviyoLogger::warning('Product sync job skipped — enabled or sync_products off', [
                'product_id' => $this->productId,
                'enabled' => (bool) config('lunar.klaviyo.enabled', false),
                'sync_products' => (bool) config('lunar.klaviyo.sync_products', false),
            ]);

            return;
        }

        KlaviyoLogger::info('Product sync job started', [
            'product_id' => $this->productId,
            'event_type' => $this->eventType->value,
            'attempt' => $this->attempts(),
            'item_external_id' => $this->itemExternalId,
        ]);

        $catalogService = app(KlaviyoCatalogService::class);

        try {
            if ($this->eventType === ProductEventType::DELETE) {
                $externalIds = $catalogService->captureExternalIdsForProductId(
                    $this->productId,
                    $this->itemExternalId,
                    $this->additionalExternalIds,
                );

                $catalogService->deleteProductByExternalIds($externalIds);
                CatalogExternalIdStore::forget($this->productId);

                KlaviyoLogger::info('Product sync job completed', [
                    'product_id' => $this->productId,
                    'event_type' => $this->eventType->value,
                    'outcome' => 'deleted',
                    'external_ids' => $externalIds,
                ]);

                return;
            }

            $product = Product::query()->with(['variants', 'collections', 'brand', 'media'])->find($this->productId);

            if (! $product) {
                KlaviyoLogger::warning('Product sync job skipped — product not found', [
                    'product_id' => $this->productId,
                    'event_type' => $this->eventType->value,
                ]);

                return;
            }

            $result = $catalogService->syncProduct($product);

            KlaviyoLogger::info('Product sync job completed', [
                'product_id' => $this->productId,
                'event_type' => $this->eventType->value,
                'outcome' => empty($result) ? 'deleted_or_skipped_unavailable' : 'upserted',
            ]);
        } catch (Exception $e) {
            KlaviyoLogger::error('Product sync job failed', [
                'product_id' => $this->productId,
                'event_type' => $this->eventType->value,
                'attempt' => $this->attempts(),
            ], $e);

            throw new FailedKlaviyoSyncException(
                'Klaviyo product sync error for product '.$this->productId.'. '.$e->getMessage()
            );
        }
    }
}
