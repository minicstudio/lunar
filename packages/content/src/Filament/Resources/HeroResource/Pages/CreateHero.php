<?php

namespace Lunar\Content\Filament\Resources\HeroResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Lunar\Content\Filament\Resources\HeroResource;
use Lunar\Content\Models\ContentBlock;

class CreateHero extends BaseCreateRecord
{
    protected static string $resource = HeroResource::class;

    /**
     * Mutate form data before creating the record to inject the type and sort order.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'hero';
        $data['sort_order'] = (int) ContentBlock::query()
            ->ofType('hero')
            ->max('sort_order') + 1;

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
