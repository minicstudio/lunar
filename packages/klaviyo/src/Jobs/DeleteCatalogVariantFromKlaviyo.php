<?php

namespace Lunar\Klaviyo\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Lunar\Klaviyo\Exceptions\FailedKlaviyoSyncException;
use Lunar\Klaviyo\Services\KlaviyoCatalogService;
use Lunar\Klaviyo\Support\KlaviyoLogger;

class DeleteCatalogVariantFromKlaviyo implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries;

    public array $backoff;

    public function __construct(
        public string $variantExternalId,
        public ?int $productId = null,
    ) {
        $this->tries = config('lunar.klaviyo.retry.max_attempts', 4);
        $this->backoff = config('lunar.klaviyo.retry.backoff', [60, 300, 3600]);
    }

    public function uniqueId(): string
    {
        return 'klaviyo-catalog-variant-delete-'.$this->variantExternalId;
    }

    public function handle(): void
    {
        if (! config('lunar.klaviyo.enabled', false)
            || ! config('lunar.klaviyo.sync_products', false)) {
            return;
        }

        try {
            app(KlaviyoCatalogService::class)->deleteCatalogVariant($this->variantExternalId);

            if ($this->productId) {
                $product = \Lunar\Models\Product::query()->withCount('variants')->find($this->productId);

                if ($product && $product->variants_count === 0) {
                    dispatch(SyncProductToKlaviyo::fromProduct($product, \Lunar\Enums\ProductEventType::DELETE));
                } elseif ($product) {
                    dispatch(SyncProductToKlaviyo::fromProduct($product, \Lunar\Enums\ProductEventType::UPDATE));
                }
            }
        } catch (Exception $e) {
            KlaviyoLogger::error('Catalog variant delete job failed', [
                'variant_external_id' => $this->variantExternalId,
                'product_id' => $this->productId,
            ], $e);

            throw new FailedKlaviyoSyncException(
                'Klaviyo catalog variant delete error for '.$this->variantExternalId.'. '.$e->getMessage()
            );
        }
    }
}
