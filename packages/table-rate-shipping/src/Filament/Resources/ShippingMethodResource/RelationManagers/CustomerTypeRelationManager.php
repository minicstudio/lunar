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
                    ->modalHeading('Attach Customer Type')
                    ->preloadRecordSelect()
                    ->label('Attach Customer Type'),
            ])
            ->columns([
                Tables\Columns\TextColumn::make('label')->label('Customer Type'),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ]);
    }
}
