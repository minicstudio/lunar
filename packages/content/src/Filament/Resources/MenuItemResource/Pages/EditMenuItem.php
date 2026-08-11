<?php

namespace Lunar\Content\Filament\Resources\MenuItemResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Content\Filament\Resources\MenuItemResource;
use Lunar\Content\Models\ContentBlock;

class EditMenuItem extends BaseEditRecord
{
    protected static string $resource = MenuItemResource::class;

    /**
     * Get the page title.
     */
    public function getTitle(): string|Htmlable
    {
        return __('lunarpanel.content::menu_item.edit.label');
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
     * Wrap legacy plain-string labels into locale maps for the form.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['data'] = ContentBlock::wrapPlainStringsAsTranslations(
            $data['data'] ?? [],
            ['label']
        );

        return parent::mutateFormDataBeforeFill($data);
    }

    /**
     * Drop fields that do not apply to the selected link type.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['data'] = CreateMenuItem::normalizeLinkPayload($data['data'] ?? []);

        return parent::mutateFormDataBeforeSave($data);
    }
}
