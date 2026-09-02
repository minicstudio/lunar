<?php

namespace Lunar\Loyalty\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;
use Lunar\Loyalty\Facades\Loyalty;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyAccountManager;

class LoyaltyAccountRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'loyaltyAccount';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('lunarpanel.loyalty::plugin.customer.loyalty_title');
    }

    public function getDefaultForm(Form $form): Form
    {
        return $form->schema([
            TextInput::make('balance')
                ->label(__('lunarpanel.loyalty::plugin.fields.display_balance'))
                ->disabled(),
            TextInput::make('available_balance')
                ->label(__('lunarpanel.loyalty::plugin.fields.available_balance'))
                ->disabled()
                ->dehydrated(false)
                ->formatStateUsing(fn (?LoyaltyAccount $record) => $record?->available_balance ?? 0),
            TextInput::make('lifetime_earned')
                ->label(__('lunarpanel.loyalty::plugin.fields.lifetime_earned'))
                ->disabled(),
            TextInput::make('lifetime_spent')
                ->label(__('lunarpanel.loyalty::plugin.fields.lifetime_spent'))
                ->disabled(),
        ]);
    }

    public function getDefaultTable(Table $table): Table
    {
        return $table
            ->heading(__('lunarpanel.loyalty::plugin.customer.transactions_heading'))
            ->query(
                fn () => $this->getOwnerRecord()->loyaltyAccount?->transactions() ?? LoyaltyAccount::query()->whereRaw('1 = 0')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('lunarpanel.loyalty::plugin.fields.date'))
                    ->dateTime(),
                TextColumn::make('type')
                    ->label(__('lunarpanel.loyalty::plugin.fields.type'))
                    ->badge(),
                TextColumn::make('points')
                    ->label(__('lunarpanel.loyalty::plugin.fields.points')),
                TextColumn::make('remaining_points')
                    ->label(__('lunarpanel.loyalty::plugin.fields.remaining_points')),
                TextColumn::make('event_key')
                    ->label(__('lunarpanel.loyalty::plugin.fields.event_key'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expires_at')
                    ->label(__('lunarpanel.loyalty::plugin.fields.expires_at'))
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createAccount')
                    ->label(__('lunarpanel.loyalty::plugin.actions.create_account'))
                    ->visible(fn () => ! $this->getOwnerRecord()->loyaltyAccount)
                    ->action(function () {
                        app(LoyaltyAccountManager::class)->firstOrCreateForCustomer($this->getOwnerRecord());
                        $this->dispatch('refresh-relation-manager');
                    }),
                Tables\Actions\Action::make('adjust')
                    ->label(__('lunarpanel.loyalty::plugin.actions.adjust'))
                    ->visible(fn () => (bool) $this->getOwnerRecord()->loyaltyAccount)
                    ->form([
                        TextInput::make('points')
                            ->label(__('lunarpanel.loyalty::plugin.fields.adjust_points'))
                            ->integer()
                            ->required()
                            ->helperText(__('lunarpanel.loyalty::plugin.fields.adjust_points_help')),
                        Textarea::make('reason')
                            ->label(__('lunarpanel.loyalty::plugin.fields.reason'))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $account = $this->getOwnerRecord()->loyaltyAccount;
                        Loyalty::manualAdjust($account, (int) $data['points'], $data['reason']);
                        $this->dispatch('refresh-relation-manager');
                    }),
            ])
            ->actions([])
            ->bulkActions([]);
    }
}
