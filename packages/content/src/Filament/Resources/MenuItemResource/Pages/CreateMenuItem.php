<?php

namespace Lunar\Content\Filament\Resources\MenuItemResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Lunar\Content\Filament\Resources\MenuItemResource;
use Lunar\Content\Models\ContentBlock;

class CreateMenuItem extends BaseCreateRecord
{
    protected static string $resource = MenuItemResource::class;

    /**
     * Mutate form data before creating the record to inject the type and sort order.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'menu_item';
        $data['sort_order'] = (int) ContentBlock::query()
            ->ofType('menu_item')
            ->max('sort_order') + 1;
        $data['data'] = static::normalizeLinkPayload($data['data'] ?? []);

        return $data;
    }

    /**
     * Redirect to edit after creation.
     */
    protected function getRedirectUrl(): string
    {
        return MenuItemResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * Drop fields that do not apply to the selected link type.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function normalizeLinkPayload(array $payload): array
    {
        $linkType = $payload['link_type'] ?? null;

        if ($linkType !== 'collection') {
            unset($payload['collection_id']);
        }

        if ($linkType !== 'cms_page') {
            unset($payload['cms_page']);
        }

        if ($linkType !== 'custom_url') {
            unset($payload['custom_url']);
        }

        if ($linkType === 'custom_url' && isset($payload['custom_url']) && is_string($payload['custom_url'])) {
            $payload['custom_url'] = trim($payload['custom_url']);
        }

        return $payload;
    }
}
