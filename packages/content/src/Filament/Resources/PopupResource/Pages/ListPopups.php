<?php

namespace Lunar\Content\Filament\Resources\PopupResource\Pages;

use Filament\Actions\CreateAction;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Content\Filament\Resources\PopupResource;

class ListPopups extends BaseListRecords
{
    protected static string $resource = PopupResource::class;

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
