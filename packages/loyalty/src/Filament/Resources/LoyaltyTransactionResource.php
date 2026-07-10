<?php

namespace Lunar\Loyalty\Filament\Resources;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lunar\Admin\Support\Resources\BaseResource;
use Lunar\Loyalty\Filament\Resources\LoyaltyTransactionResource\Pages\ListLoyaltyTransactions;
use Lunar\Loyalty\Models\LoyaltyTransaction;

class LoyaltyTransactionResource extends BaseResource
{
    protected static ?string $permission = 'sales:loyalty:manage';

    protected static ?string $model = LoyaltyTransaction::class;

    public static function getLabel(): string
    {
        return __('lunarpanel.loyalty::plugin.label');
    }

    public static function getPluralLabel(): string
    {
        return __('lunarpanel.loyalty::plugin.plural_label');
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-gift';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel.loyalty::plugin.navigation.group');
    }

    public static function getDefaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('loyaltyAccount.customer.fullName')
                    ->label(__('lunarpanel.loyalty::plugin.fields.customer'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('lunarpanel.loyalty::plugin.fields.date'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('lunarpanel.loyalty::plugin.fields.type'))
                    ->badge(),
                TextColumn::make('points')
                    ->label(__('lunarpanel.loyalty::plugin.fields.points'))
                    ->sortable(),
                TextColumn::make('remaining_points')
                    ->label(__('lunarpanel.loyalty::plugin.fields.remaining_points')),
                TextColumn::make('event_key')
                    ->label(__('lunarpanel.loyalty::plugin.fields.event_key'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyTransactions::route('/'),
        ];
    }
}
