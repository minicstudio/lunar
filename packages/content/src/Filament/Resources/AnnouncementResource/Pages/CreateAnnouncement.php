<?php

namespace Lunar\Content\Filament\Resources\AnnouncementResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Lunar\Content\Filament\Resources\AnnouncementResource;

class CreateAnnouncement extends BaseCreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    /**
     * Mutate form data before creating the record to inject the type.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'announcement';
        $data['data'] ??= [];
        $data['data']['is_closable'] = (bool) ($data['is_closable'] ?? false);
        unset($data['is_closable']);

        return $data;
    }

    /**
     * Redirect to edit after creation.
     */
    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
