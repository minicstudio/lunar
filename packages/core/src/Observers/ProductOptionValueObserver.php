<?php

namespace Lunar\Observers;

use Lunar\Models\Contracts\ProductOptionValue as ProductOptionValueContract;
use Lunar\Models\ProductOptionValue;

class ProductOptionValueObserver
{
    /**
     * Handle the ProductOptionValue "saved" event.
     *
     * Option values are stored on the parent product option search document.
     */
    public function saved(ProductOptionValueContract $productOptionValue): void
    {
        $this->syncParentOption($productOptionValue);
    }

    /**
     * Handle the ProductOptionValue "deleting" event.
     */
    public function deleting(ProductOptionValueContract $productOptionValue): void
    {
        /** @var ProductOptionValue $productOptionValue */
        $productOptionValue->variants()->detach();
    }

    /**
     * Handle the ProductOptionValue "deleted" event.
     *
     * Removing a value must rewrite the parent option document, otherwise
     * search indexes such as Algolia keep the deleted value.
     */
    public function deleted(ProductOptionValueContract $productOptionValue): void
    {
        $this->syncParentOption($productOptionValue);
    }

    protected function syncParentOption(ProductOptionValueContract $productOptionValue): void
    {
        if (ProductOptionObserver::isDeletingProductOption()) {
            return;
        }

        /** @var ProductOptionValue $productOptionValue */
        $option = $productOptionValue->option;

        if (! $option || ! method_exists($option, 'searchable')) {
            return;
        }

        $option->unsetRelation('values');
        $option->searchable();
    }
}
