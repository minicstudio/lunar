<?php

namespace Lunar\Content\Filament\Resources\FaqItemResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Lunar\Content\Filament\Resources\FaqItemResource;
use Lunar\Content\Models\ContentBlock;

class CreateFaqItem extends BaseCreateRecord
{
    protected static string $resource = FaqItemResource::class;

    /**
     * Mutate form data before creating the record to inject the type and sort order.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'faq_item';
        $data['sort_order'] = (int) ContentBlock::query()
            ->ofType('faq_item')
            ->max('sort_order') + 1;

        return $data;
    }

    /**
     * Redirect to edit after creation.
     */
    protected function getRedirectUrl(): string
    {
        return FaqItemResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
