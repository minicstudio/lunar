<?php

namespace Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Events\DiscountLimitationAttached;
use Lunar\Admin\Events\DiscountLimitationBulkDetached;
use Lunar\Admin\Events\DiscountLimitationDetached;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;

class BrandLimitationRelationManager extends BaseRelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'brands';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('lunarpanel::brand.plural_label');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getDefaultTable(Table $table): Table
    {

        return $table
            ->heading(
                __('lunarpanel::discount.relationmanagers.brands.title')
            )
            ->description(
                __('lunarpanel::discount.relationmanagers.brands.description')
            )
            ->paginated(false)
            ->headerActions([
                AttachAction::make()->form(fn (AttachAction $action): array => [
                    $action->getRecordSelect(),
                    Select::make('type')
                        ->label(__('lunarpanel::discount.relationmanagers.brands.form.type.label'))
                        ->options(
                            fn () => [
                                'limitation' => __('lunarpanel::discount.relationmanagers.brands.form.type.options.limitation.label'),
                                'exclusion' => __('lunarpanel::discount.relationmanagers.brands.form.type.options.exclusion.label'),
                            ]
                        )->default('limitation'),
                ])->recordTitle(function ($record) {
                    return $record->name;
                })->preloadRecordSelect()
                    ->label(
                        __('lunarpanel::discount.relationmanagers.brands.actions.attach.label')
                    )
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.brands.actions.attach.label')
                    )
                    ->recordSelectSearchColumns(['name'])
                    ->after(function ($record) {
                        DiscountLimitationAttached::dispatch($this->getOwnerRecord());
                    }),
            ])->columns([
                TextColumn::make('name')
                    ->label(
                        __('lunarpanel::discount.relationmanagers.brands.table.name.label')
                    ),
                TextColumn::make('pivot.type')
                    ->label(
                        __('lunarpanel::discount.relationmanagers.brands.table.type.label')
                    )->formatStateUsing(
                        fn (string $state) => __("lunarpanel::discount.relationmanagers.brands.table.type.{$state}.label")
                    ),
            ])->recordActions([
                DetachAction::make()
                    ->after(function ($record) {
                        DiscountLimitationDetached::dispatch($this->getOwnerRecord());
                    })
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.brands.actions.detach.heading')
                    ),
            ])->toolbarActions([
                DetachBulkAction::make()
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.brands.actions.detach.bulk.heading')
                    )
                    ->after(function () {
                        DiscountLimitationBulkDetached::dispatch();
                    }),
            ]);
    }
}
