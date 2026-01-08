<?php

namespace Lunar\Shipping\Filament\Resources\ShippingMethodResource\RelationManagers;

use Filament\Tables;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;

class CustomerTypeRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'customerTypes';

    public function getDefaultTable(Tables\Table $table): Tables\Table
    {
        return $table
            ->paginated(false)
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->recordTitle(fn ($record) => $record->label)
                    ->modalHeading(__('lunarpanel.shipping::relationmanagers.shipping_methods.customer_types.heading'))
                    ->preloadRecordSelect()
                    ->label(__('lunarpanel.shipping::relationmanagers.shipping_methods.customer_types.heading')),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('label')->label(__('lunarpanel.shipping::relationmanagers.shipping_methods.customer_types.title')),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ]);
    }
}
