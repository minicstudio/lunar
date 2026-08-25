<?php

namespace Lunar\Content\Filament\Resources\AnnouncementResource\Pages;

use Filament\Actions\CreateAction;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Content\Filament\Resources\AnnouncementResource;

class ListAnnouncements extends BaseListRecords
{
    protected static string $resource = AnnouncementResource::class;

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
