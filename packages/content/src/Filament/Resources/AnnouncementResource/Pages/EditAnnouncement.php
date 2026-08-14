<?php

namespace Lunar\Content\Filament\Resources\AnnouncementResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Content\Filament\Resources\AnnouncementResource;
use Lunar\Content\Models\ContentBlock;

class EditAnnouncement extends BaseEditRecord
{
    protected static string $resource = AnnouncementResource::class;

    /**
     * Get the page title.
     */
    public function getTitle(): string|Htmlable
    {
        return __('lunarpanel.content::announcement.edit.label');
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

    /**
     * Hydrate the virtual closable toggle from the JSON payload.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['is_closable'] = (bool) ($data['data']['is_closable'] ?? false);
        $data['data'] = ContentBlock::wrapPlainStringsAsTranslations(
            $data['data'] ?? [],
            ['text', 'link_label']
        );

        return parent::mutateFormDataBeforeFill($data);
    }

    /**
     * Persist the virtual closable toggle back into the JSON payload.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['data'] ??= [];
        $data['data']['is_closable'] = (bool) ($data['is_closable'] ?? false);
        unset($data['is_closable']);

        return parent::mutateFormDataBeforeSave($data);
    }
}
