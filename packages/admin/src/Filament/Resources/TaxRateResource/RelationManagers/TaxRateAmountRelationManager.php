<?php

namespace Lunar\Admin\Filament\Resources\TaxRateResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Lunar\Models\TaxRateAmount;

class TaxRateAmountRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'taxRateAmounts';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('lunarpanel::relationmanagers.tax_rate_amounts.title');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('tax_class_id')
                ->required()
                ->label(__('lunarpanel::relationmanagers.tax_rate_amounts.form.tax_class.label'))
                ->unique(
                    TaxRateAmount::class,
                    'tax_class_id',
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->when(
                        $this->getOwnerRecord(),
                        fn ($query, $value) => $query->where('tax_rate_id', $value->id)
                    )
                )
                ->relationship(name: 'taxClass', titleAttribute: 'name'),
            TextInput::make('percentage')->numeric()->required()
                ->label(__('lunarpanel::relationmanagers.tax_rate_amounts.form.percentage.label')),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(
                __('lunarpanel::relationmanagers.tax_rate_amounts.table.description')
            )
            ->paginated(false)
            ->headerActions([
                Tables\Actions\CreateAction::make('create')
                    ->label(__('lunarpanel::relationmanagers.tax_rate_amounts.table.actions.create.label'))
                    ->modalHeading(
                        __('lunarpanel::relationmanagers.tax_rate_amounts.table.actions.create.heading')
                    ),
            ])->columns([
                Tables\Columns\TextColumn::make('taxClass.name')->label(
                    __('lunarpanel::relationmanagers.tax_rate_amounts.table.tax_class.label')
                ),
                Tables\Columns\TextColumn::make('percentage')->label(
                    __('lunarpanel::relationmanagers.tax_rate_amounts.table.percentage.label')
                ),
            ])->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading(
                        __('lunarpanel::relationmanagers.tax_rate_amounts.table.actions.edit.heading')
                    ),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(
                        __('lunarpanel::relationmanagers.tax_rate_amounts.table.actions.delete.heading')
                    ),
            ]);
    }
}
