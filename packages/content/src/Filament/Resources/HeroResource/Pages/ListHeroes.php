<?php

namespace Lunar\Content\Filament\Resources\HeroResource\Pages;

use Filament\Actions\CreateAction;
use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Content\Filament\Resources\HeroResource;

class ListHeroes extends BaseListRecords
{
    protected static string $resource = HeroResource::class;

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
