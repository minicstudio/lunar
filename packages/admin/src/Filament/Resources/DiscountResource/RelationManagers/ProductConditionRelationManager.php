<?php

namespace Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Product;

class ProductConditionRelationManager extends BaseRelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'purchasables';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('lunarpanel::discount.relationmanagers.conditions.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getDefaultTable(Table $table): Table
    {

        return $table
            ->heading(
                __('lunarpanel::discount.relationmanagers.conditions.title')
            )
            ->description(
                __('lunarpanel::discount.relationmanagers.conditions.description')
            )
            ->paginated(false)
            ->modifyQueryUsing(
                fn ($query) => $query->whereIn('type', ['condition'])
                    ->wherePurchasableType(Product::morphName())
                    ->whereHas('purchasable')
            )
            ->headerActions([
                Tables\Actions\CreateAction::make()->form([
                    Forms\Components\MorphToSelect::make('purchasable')
                        ->searchable(true)
                        ->label(
                            __('lunarpanel::discount.relationmanagers.conditions.form.purchasable.label')
                        )
                        ->types([
                            Forms\Components\MorphToSelect\Type::make(Product::modelClass())
                                ->titleAttribute('name.en')
                                ->label(__('lunarpanel::discount.relationmanagers.conditions.form.purchasable.types.product.label'))
                                ->getSearchResultsUsing(static function (Forms\Components\Select $component, string $search): array {
                                    return get_search_builder(Product::modelClass(), $search)
                                        ->get()
                                        ->mapWithKeys(fn (ProductContract $record): array => [$record->getKey() => $record->attr('name')])
                                        ->all();
                                }),
                        ]),
                ])->label(
                    __('lunarpanel::discount.relationmanagers.conditions.actions.attach.label')
                )
                ->modalHeading(
                    __('lunarpanel::discount.relationmanagers.conditions.actions.attach.modal.heading')
                )
                ->mutateFormDataUsing(function (array $data) {
                    $data['type'] = 'condition';

                    return $data;
                }),
            ])->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('purchasable.thumbnail')
                    ->collection(config('lunar.media.collection'))
                    ->conversion('small')
                    ->limit(1)
                    ->square()
                    ->label(''),
                Tables\Columns\TextColumn::make('purchasable.attribute_data.name')
                    ->label(
                        __('lunarpanel::discount.relationmanagers.conditions.table.name.label')
                    )
                    ->formatStateUsing(
                        fn (Model $record) => $record->purchasable->attr('name')
                    ),
            ])->actions([
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(__('lunarpanel::discount.relationmanagers.conditions.actions.delete.modal.heading'))
            ])->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->modalHeading(__('lunarpanel::discount.relationmanagers.conditions.actions.delete.modal.bulk.heading')),
            ]);
    }
}
