<?php

namespace Lunar\Shipping\Filament\Resources\ShippingMethodResource\Pages;

use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Support\Facades\FilamentIcon;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource\RelationManagers\CustomerTypeRelationManager;
use Lunar\Admin\Support\Pages\BaseManageRelatedRecords;
use Lunar\Shipping\Filament\Resources\ShippingMethodResource;

class ManageShippingMethodLimitations extends BaseManageRelatedRecords
{
    protected static string $resource = ShippingMethodResource::class;

    protected static string $relationship = 'customerTypes';

    public function getTitle(): string
    {
        return __('lunarpanel.shipping::shippingmethod.pages.limitations.label');
    }

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::discount-limitations');
    }

    public static function getNavigationLabel(): string
    {
        return __('lunarpanel.shipping::shippingmethod.pages.limitations.label');
    }

    public function getRelationManagers(): array
    {
        return [
            RelationGroup::make('Limitations', [
                CustomerTypeRelationManager::make([
                    'description' => __('lunarpanel.shipping::relationmanagers.shipping_methods.customer_types.description'),
                ]),
            ]),
        ];
    }
}
