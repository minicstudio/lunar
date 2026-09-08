<?php

namespace Lunar\Shipping\Filament\Resources\ShippingMethodResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\AttachAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\DetachAction;
use Filament\Tables;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;

class CustomerTypeRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'customerTypes';

    public function getDefaultTable(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->headerActions([
                AttachAction::make()
                    ->recordTitle(fn ($record) => $record->label)
                    ->modalHeading(__('lunarpanel.shipping::relationmanagers.shipping_methods.customer_types.heading'))
                    ->preloadRecordSelect()
                    ->label(__('lunarpanel.shipping::relationmanagers.shipping_methods.customer_types.heading')),
            ])
            ->columns([
                TextColumn::make('label')->label(__('lunarpanel.shipping::relationmanagers.shipping_methods.customer_types.title')),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
