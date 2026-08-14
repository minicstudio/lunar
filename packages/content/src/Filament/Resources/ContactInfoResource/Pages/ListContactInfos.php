<?php

namespace Lunar\Content\Filament\Resources\ContactInfoResource\Pages;

use Lunar\Admin\Support\Pages\BaseListRecords;
use Lunar\Content\Filament\Resources\ContactInfoResource;

class ListContactInfos extends BaseListRecords
{
    protected static string $resource = ContactInfoResource::class;

    /**
     * Contact details are a singleton — send the admin to create or edit.
     */
    public function mount(): void
    {
        $record = ContactInfoResource::getEloquentQuery()->first();

        $this->redirect(
            $record
                ? ContactInfoResource::getUrl('edit', ['record' => $record])
                : ContactInfoResource::getUrl('create')
        );
    }
}
