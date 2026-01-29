<?php

namespace Lunar\Shipping\Filament\Resources\ShippingMethodResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource;

class EditShippingMethod extends BaseEditRecord
{
    protected static string $resource = ShippingMethodResource::class;

    public static function getNavigationLabel(): string
    {
        return __('lunarpanel.shipping::shippingmethod.pages.edit.navigation_label');
    }

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-panels::resources/pages/edit-record.title', [
            'label' => __('lunarpanel.shipping::shippingmethod.label'),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function getRelationManagers(): array
    {
        // Return only the relation managers you want to show under the edit form.
        // If you want none, return an empty array.
        return [];
    }
}
