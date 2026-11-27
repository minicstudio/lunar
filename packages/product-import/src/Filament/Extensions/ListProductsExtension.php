<?php

namespace Lunar\ProductImport\Filament\Extensions;

use Filament\Actions\Action;
use Lunar\Admin\Support\Extending\ListPageExtension;
use Lunar\ProductImport\Filament\Actions\NewImportAction;
use Lunar\ProductImport\Filament\Resources\ProductImportResource;

/**
 * Adds the **Import** button to the product list for staff who may import.
 */
class ListProductsExtension extends ListPageExtension
{
    /**
     * Append the import action.
     *
     * @param  array<int, Action>  $actions
     * @return array<int, Action>
     */
    public function headerActions(array $actions): array
    {
        if (! ProductImportResource::canAccess()) {
            return $actions;
        }

        return [
            ...$actions,
            NewImportAction::make('importProducts')
                ->label(__('lunarpanel.product-import::plugin.actions.import'))
                ->color('gray'),
        ];
    }
}
