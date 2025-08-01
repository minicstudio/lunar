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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
