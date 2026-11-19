<?php

namespace Lunar\Observers;

use Lunar\Models\Contracts\ProductOption as ProductOptionContract;
use Lunar\Models\Contracts\ProductOptionValue as ProductOptionValueContract;
use Lunar\Models\ProductOption;

class ProductOptionObserver
{
    protected static bool $deletingProductOption = false;

    public static function isDeletingProductOption(): bool
    {
        return static::$deletingProductOption;
    }

    /**
     * Handle the ProductOption "deleting" event.
     */
    public function deleting(ProductOptionContract $productOption): void
    {
        static::$deletingProductOption = true;

        try {
            /** @var ProductOption $productOption */
            $productOption->products()->detach();
            $productOption->values()->each(
                fn (ProductOptionValueContract $optionValue) => $optionValue->delete()
            );
        } finally {
            static::$deletingProductOption = false;
        }
    }
}
