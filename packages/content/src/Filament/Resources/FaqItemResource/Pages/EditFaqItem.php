<?php

namespace Lunar\Content\Filament\Resources\FaqItemResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Contracts\Support\Htmlable;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Content\Filament\Resources\FaqItemResource;
use Lunar\Content\Models\ContentBlock;

class EditFaqItem extends BaseEditRecord
{
    protected static string $resource = FaqItemResource::class;

    /**
     * Get the page title.
     */
    public function getTitle(): string|Htmlable
    {
        return __('lunarpanel.content::faq_item.edit.label');
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
     * Wrap legacy plain-string fields into locale maps for the form.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['data'] = ContentBlock::wrapPlainStringsAsTranslations(
            $data['data'] ?? [],
            ['question', 'answer']
        );

        return parent::mutateFormDataBeforeFill($data);
    }
}
