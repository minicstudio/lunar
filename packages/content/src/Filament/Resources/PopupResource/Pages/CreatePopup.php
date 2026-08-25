<?php

namespace Lunar\Content\Filament\Resources\PopupResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Lunar\Content\Filament\Resources\PopupResource;

class CreatePopup extends BaseCreateRecord
{
    protected static string $resource = PopupResource::class;

    /**
     * Mutate form data before creating the record to inject the type.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'popup';
        $data['data'] ??= [];
        $data['data']['show_once'] = (bool) ($data['show_once'] ?? true);
        $data['data']['delay_seconds'] = (int) ($data['data']['delay_seconds'] ?? 5);
        $data['data']['width_percentage'] = min(100, max(30, (int) ($data['data']['width_percentage'] ?? 60)));
        $data['data']['display_on'] = array_values($data['data']['display_on'] ?? []);
        unset($data['show_once']);

        return $data;
    }

    /**
     * Redirect to edit after creation.
     */
    protected function getRedirectUrl(): string
    {
        return PopupResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
