<?php

namespace Lunar\Admin\Filament\Resources\ProductOptionResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Events\ProductOptionValueUpdated;
use Lunar\Admin\Support\Forms\Components\TranslatedText;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;
use Lunar\Admin\Support\Tables\Columns\TranslatedTextColumn;

class ValuesRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'values';

    public function getTableRecordTitle(Model $record): ?string
    {
        return $record->translate('name');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('lunarpanel::productoption.values.title');
    }

    public function getDefaultForm(Form $form): Form
    {
        return $form
            ->schema([
                TranslatedText::make('name')
                    ->label(__('lunarpanel::productoption.values.form.name.label'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function getDefaultTable(Table $table): Table
    {
        return $table

            ->columns([
                TranslatedTextColumn::make('name')
                    ->label(__('lunarpanel::productoption.values.table.name.label')),
                Tables\Columns\TextColumn::make('position')
                    ->label(__('lunarpanel::productoption.values.table.position.label')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('lunarpanel::productoption.values.table.actions.create.label'))
                    ->modalHeading(__('lunarpanel::productoption.values.table.actions.create.heading'))
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading(__('lunarpanel::productoption.values.table.actions.edit.heading'))
                    ->after(function (Model $record) {
                        ProductOptionValueUpdated::dispatch($record);
                    }),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(__('lunarpanel::productoption.values.table.actions.delete.heading'))
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading(__('lunarpanel::productoption.values.table.actions.delete.bulk.heading')),
                ]),
            ])
            ->defaultSort('position', 'asc')
            ->reorderable('position');
    }
}
