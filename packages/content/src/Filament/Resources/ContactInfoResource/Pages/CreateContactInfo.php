<?php

namespace Lunar\Content\Filament\Resources\ContactInfoResource\Pages;

use Lunar\Admin\Support\Pages\BaseCreateRecord;
use Lunar\Content\Filament\Resources\ContactInfoResource;

class CreateContactInfo extends BaseCreateRecord
{
    protected static string $resource = ContactInfoResource::class;

    /**
     * Mutate form data before creating the record to inject the type and key.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'contact_info';
        $data['key'] = 'default';
        $data['sort_order'] = 0;

        return $data;
    }

    /**
     * Redirect to edit after creation.
     */
    protected function getRedirectUrl(): string
    {
        return ContactInfoResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * If a contact_info row already exists, bounce to edit instead of creating a second.
     */
    public function mount(): void
    {
        $existing = ContactInfoResource::getEloquentQuery()->first();

        if ($existing) {
            $this->redirect(ContactInfoResource::getUrl('edit', ['record' => $existing]));

            return;
        }

        parent::mount();
    }
}
