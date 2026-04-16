<?php

namespace Lunar\Observers;

use Lunar\Events\ProductPublished;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Product;

class ProductObserver
{
    /**
     * Handle the Product "updating" event.
     */
    public function updating(ProductContract $product): void
    {
        /** @var Product $product */
        // Check if status is changing to 'published'
        if ($product->getOriginal('status') !== 'published' && $product->status === 'published') {
            $product->published_at = now();
        }

        // Check if status is changing to 'draft'
        if ($product->status === 'draft' && $product->getOriginal('status') !== 'draft') {
            $product->published_at = null;
        }
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(ProductContract $product): void
    {
        /** @var Product $product */
        // Fire ProductPublished event if status was changed to 'published'
        if ($product->wasChanged('status') && $product->status === 'published') {
            ProductPublished::dispatch($product);
        }
    }

    /**
     * Handle the ProductVariant "deleted" event.
     */
    public function deleting(ProductContract $product): void
    {
        if ($product->isForceDeleting()) {
            $product->variants()->withTrashed()->get()->each->forceDelete();

            $product->collections()->detach();

            $product->customerGroups()->detach();

            $product->urls()->delete();

            $product->productOptions()->detach();

            $product->associations()->delete();

            $product->inverseAssociations()->delete();

            $product->channels()->detach();

            $product->tags()->detach();
        } else {
            $product->variants()->get()->each->delete();
        }
    }

    public function restored(ProductContract $product): void
    {
        $product->variants()->withTrashed()->get()->each->restore();
    }
}
