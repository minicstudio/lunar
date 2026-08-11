<?php

namespace Lunar\Content\Filament\Resources\HeroResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Lunar\Content\Filament\Resources\HeroResource;

class CreateHero extends BaseCreateRecord
{
    protected static string $resource = HeroResource::class;

    /**
     * Mutate form data before creating the record to inject the type.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'hero';

        return $data;
    }

    /**
     * Redirect to edit after creation.
     */
    protected function getRedirectUrl(): string
    {
        return HeroResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
