<?php

namespace Lunar\Content\Filament\Resources\HeroResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Content\Filament\Resources\HeroResource;

class EditHero extends BaseEditRecord
{
    protected static string $resource = HeroResource::class;

    /**
     * Get the page title.
     */
    public function getTitle(): string|Htmlable
    {
        return __('lunarpanel.content::hero.edit.label');
    }

    /**
     * Get the default header actions for the page.
     */
    protected function getDefaultHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
