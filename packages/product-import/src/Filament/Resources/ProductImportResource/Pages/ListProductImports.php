<?php

namespace Lunar\ProductImport\Filament\Resources\ProductImportResource\Pages;

use Filament\Actions\Action;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\ProductImport\Analysis\AiAvailability;
use Lunar\ProductImport\Filament\Actions\NewImportAction;
use Lunar\ProductImport\Filament\Resources\ProductImportResource;

class ListProductImports extends BaseListRecords
{
    /**
     * The resource class for the import list.
     */
    protected static string $resource = ProductImportResource::class;

    /**
     * The AI provider and model in use, or why uploads are disabled.
     */
    public function getSubheading(): ?string
    {
        $ai = app(AiAvailability::class);

        return $ai->isConfigured()
            ? $ai->label()
            : __('lunarpanel.product-import::analysis.failures.not_configured');
    }

    protected function getDefaultHeaderActions(): array
    {
        return [
            Action::make('profiles')
                ->label(__('lunarpanel.product-import::ui.profiles.title'))
                ->color('gray')
                ->url(ProductImportResource::getUrl('profiles')),
            NewImportAction::make(),
        ];
    }
}
