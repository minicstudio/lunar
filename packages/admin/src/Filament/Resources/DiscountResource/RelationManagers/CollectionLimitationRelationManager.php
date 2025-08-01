<?php

namespace Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;
use Lunar\Models\Collection;

class CollectionLimitationRelationManager extends BaseRelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'collections';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('lunarpanel::collection.plural_label');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getDefaultTable(Table $table): Table
    {

        return $table
            ->heading(
                __('lunarpanel::discount.relationmanagers.collections.title')
            )
            ->description(
                __('lunarpanel::discount.relationmanagers.collections.description')
            )
            ->paginated(false)
            ->headerActions([
                Tables\Actions\AttachAction::make()->form(fn (Tables\Actions\AttachAction $action): array => [
                    $action->getRecordSelect(),
                    Select::make('type')
                        ->label(
                            __('lunarpanel::discount.relationmanagers.collections.form.type.label')
                        )
                        ->options(
                            fn () => [
                                'limitation' => __('lunarpanel::discount.relationmanagers.collections.form.type.options.limitation.label'),
                                'exclusion' => __('lunarpanel::discount.relationmanagers.collections.form.type.options.exclusion.label'),
                            ]
                        )->default('limitation'),
                ])->recordTitle(function ($record) {
                    return $record->attr('name');
                })->preloadRecordSelect()
                    ->label(
                        __('lunarpanel::discount.relationmanagers.collections.actions.attach.label')
                    )
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.collections.actions.attach.label')
                    ),
            ])->columns([
                Tables\Columns\TextColumn::make('attribute_data.name')
                    ->label(
                        __('lunarpanel::discount.relationmanagers.collections.table.name.label')
                    )
                    ->description(fn (Collection $record): string => $record->breadcrumb->implode(' > '))
                    ->formatStateUsing(
                        fn (Model $record) => $record->attr('name')
                    ),
                Tables\Columns\TextColumn::make('pivot.type')
                    ->label(
                        __('lunarpanel::discount.relationmanagers.collections.table.type.label')
                    )->formatStateUsing(
                        fn (string $state) => __("lunarpanel::discount.relationmanagers.collections.table.type.{$state}.label")
                    ),
            ])->actions([
                Tables\Actions\DetachAction::make()
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.collections.actions.detach.label')
                    ),
            ])->bulkActions([
                Tables\Actions\DetachBulkAction::make()
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.collections.actions.detach.bulk.label')
                    ),
            ]);
    }
}
