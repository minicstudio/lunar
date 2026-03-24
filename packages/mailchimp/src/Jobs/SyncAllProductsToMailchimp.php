<?php

namespace Lunar\Mailchimp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Enums\ProductEventType;
use Lunar\Models\Product;

class SyncAllProductsToMailchimp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $chunkSize = 100
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Product::query()
            ->with(['variants', 'collections', 'brand', 'media'])
            ->whereHas('variants', function ($variantQuery) {
                $variantQuery->where(function ($stockQuery) {
                    $stockQuery->where('stock', '>', 0)
                        ->orWhere('backorder', true);
                });
            })
            ->chunk($this->chunkSize, function ($products) {
                foreach ($products as $product) {
                    if ($product->isAvailable()) {
                        SyncProductToMailchimp::dispatch($product, ProductEventType::UPDATE);
                    }
                }
            });
    }
}
