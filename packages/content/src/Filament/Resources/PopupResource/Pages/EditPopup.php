<?php

namespace Lunar\Content\Filament\Resources\PopupResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Content\Filament\Resources\PopupResource;
use Lunar\Content\Models\ContentBlock;

class EditPopup extends BaseEditRecord
{
    protected static string $resource = PopupResource::class;

    /**
     * Get the page title.
     */
    public function getTitle(): string|Htmlable
    {
        return __('lunarpanel.content::popup.edit.label');
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
     * Hydrate the virtual show_once toggle from the JSON payload.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['show_once'] = (bool) ($data['data']['show_once'] ?? true);
        $data['data'] = ContentBlock::wrapPlainStringsAsTranslations(
            $data['data'] ?? [],
            ['title', 'body', 'cta_label']
        );

        return parent::mutateFormDataBeforeFill($data);
    }

    /**
     * Persist the virtual show_once toggle back into the JSON payload.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['data'] ??= [];
        $data['data']['show_once'] = (bool) ($data['show_once'] ?? true);
        $data['data']['delay_seconds'] = (int) ($data['data']['delay_seconds'] ?? 5);
        $data['data']['width_percentage'] = min(100, max(30, (int) ($data['data']['width_percentage'] ?? 60)));
        $data['data']['display_on'] = array_values($data['data']['display_on'] ?? []);
        unset($data['show_once']);

        return parent::mutateFormDataBeforeSave($data);
    }
}
