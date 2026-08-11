<?php

namespace Lunar\Content\Filament\Resources\ContactInfoResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Content\Filament\Resources\ContactInfoResource;
use Lunar\Content\Models\ContentBlock;

class EditContactInfo extends BaseEditRecord
{
    protected static string $resource = ContactInfoResource::class;

    /**
     * Get the page title.
     */
    public function getTitle(): string|Htmlable
    {
        return __('lunarpanel.content::contact_info.edit.label');
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
     * Wrap legacy plain-string intro into a locale map for the form.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['data'] = ContentBlock::wrapPlainStringsAsTranslations(
            $data['data'] ?? [],
            ['intro']
        );

        return parent::mutateFormDataBeforeFill($data);
    }
}
