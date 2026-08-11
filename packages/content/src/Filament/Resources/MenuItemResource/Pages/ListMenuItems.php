<?php

namespace Lunar\Content\Filament\Resources\MenuItemResource\Pages;

use Filament\Actions\CreateAction;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Content\Filament\Resources\MenuItemResource;

class ListMenuItems extends BaseListRecords
{
    protected static string $resource = MenuItemResource::class;

    /**
     * Get the default header actions for the page.
     */
    protected function getDefaultHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
