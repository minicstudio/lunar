<?php

namespace Lunar\Shipping\Filament\Resources\ShippingZoneResource\Pages;

use Filament\Actions;
use Lunar\Admin\Support\Pages\BaseEditRecord;
use Lunar\Shipping\Filament\Resources\ShippingZoneResource;

class EditShippingZone extends BaseEditRecord
{
    protected static string $resource = ShippingZoneResource::class;

    public static function getNavigationLabel(): string
    {
        return __('lunarpanel.shipping::shippingzone.pages.edit.navigation_label');
    }

    protected function getDefaultHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
