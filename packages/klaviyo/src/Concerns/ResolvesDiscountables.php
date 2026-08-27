<?php

namespace Lunar\Klaviyo\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lunar\Enums\ProductEventType;
use Lunar\Klaviyo\Jobs\SyncAllProductsToKlaviyo;
use Lunar\Klaviyo\Jobs\SyncProductToKlaviyo;
use Lunar\Models\Brand;
use Lunar\Models\Collection as CollectionModel;
use Lunar\Models\Discount;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

trait ResolvesDiscountables
{
    /**
     * Check if the discount applies globally (no product/collection/brand limitations).
     */
    protected function isGlobalDiscount(Discount $discount): bool
    {
        return ! $discount->discountables()->exists()
            && ! $discount->collections()->exists()
            && ! $discount->brands()->exists();
    }

    /**
     * Coupon discounts only apply at checkout — they never change catalog prices.
     */
    protected function discountHasCoupon(Discount $discount): bool
    {
        return filled($discount->coupon);
    }

    protected function klaviyoCatalogSyncEnabled(): bool
    {
        return config('lunar.klaviyo.enabled', false)
            && config('lunar.klaviyo.sync_products', false);
    }

    /**
     * @return Builder<\Lunar\Models\Product>
     */
    protected function getAffectedProductsQuery(Discount $discount): Builder
    {
        if ($this->isGlobalDiscount($discount)) {
            return Product::query();
        }

        return Product::query()->whereIn('id', $this->getAffectedProductIds($discount));
    }

    /**
     * @return Collection<int, int|string>
     */
    protected function getAffectedProductIds(Discount $discount): Collection
    {
        $productIds = collect();

        $productIds = $productIds->merge(
            $discount->discountables()
                ->where('discountable_type', Product::morphName())
                ->pluck('discountable_id')
        );

        $variantIds = $discount->discountables()
            ->where('discountable_type', ProductVariant::morphName())
            ->pluck('discountable_id');

        if ($variantIds->isNotEmpty()) {
            $productIds = $productIds->merge(
                ProductVariant::query()->whereIn('id', $variantIds)->pluck('product_id')
            );
        }

        $collectionsRelation = $discount->collections();
        $collectionIds = $collectionsRelation->pluck($collectionsRelation->getRelated()->getQualifiedKeyName());

        if ($collectionIds->isNotEmpty()) {
            $productIds = $productIds->merge(
                Product::query()
                    ->whereHas('collections', function ($query) use ($collectionIds) {
                        $query->whereIn($query->getModel()->getQualifiedKeyName(), $collectionIds);
                    })
                    ->pluck('id')
            );
        }

        $brandsRelation = $discount->brands();
        $brandIds = $brandsRelation->pluck($brandsRelation->getRelated()->getQualifiedKeyName());

        if ($brandIds->isNotEmpty()) {
            $productIds = $productIds->merge(
                Product::query()->whereIn('brand_id', $brandIds)->pluck('id')
            );
        }

        return $productIds->unique()->filter();
    }

    /**
     * @return list<int>
     */
    protected function resolveProductsFromDiscountRelation(string $type, int $id): array
    {
        if (is_a($type, Product::class, true)) {
            return [$id];
        }

        if (is_a($type, ProductVariant::class, true)) {
            $productId = ProductVariant::query()->whereKey($id)->value('product_id');

            return $productId ? [(int) $productId] : [];
        }

        if (is_a($type, Brand::class, true)) {
            return Product::query()->where('brand_id', $id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (is_a($type, CollectionModel::class, true)) {
            return Product::query()
                ->whereHas('collections', fn ($query) => $query->whereKey($id))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return [];
    }

    /**
     * @param  iterable<int|string>  $productIds
     */
    protected function dispatchProductSyncs(iterable $productIds): void
    {
        foreach (collect($productIds)->unique()->filter() as $productId) {
            dispatch(new SyncProductToKlaviyo((int) $productId, ProductEventType::UPDATE));
        }
    }

    protected function dispatchFullCatalogSync(): void
    {
        dispatch(new SyncAllProductsToKlaviyo);
    }
}
