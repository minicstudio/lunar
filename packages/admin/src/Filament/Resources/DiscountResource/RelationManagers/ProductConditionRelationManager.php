<?php

namespace Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;
use Lunar\Admin\Support\Tables\Columns\ThumbnailImageColumn;
use Lunar\Models\Collection;
use Lunar\Models\Contracts\Collection as CollectionContract;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Contracts\ProductVariant as ProductVariantContract;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class ProductConditionRelationManager extends BaseRelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'discountables';

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
                    ->whereIn('discountable_type', [Collection::morphName(), Product::morphName(), ProductVariant::morphName()])
                    ->whereHas('discountable')
            )
            ->headerActions([
                Tables\Actions\CreateAction::make()->form([
                    Forms\Components\MorphToSelect::make('discountable')
                        ->searchable(true)
                        ->label(
                            __('lunarpanel::discount.relationmanagers.conditions.form.purchasable.label')
                        )
                        ->types([
                            Forms\Components\MorphToSelect\Type::make(Collection::modelClass())
                                ->titleAttribute('name.en')
                                ->getSearchResultsUsing(static function (Forms\Components\Select $component, string $search): array {
                                    return get_search_builder(Collection::modelClass(), $search)
                                        ->get()
                                        ->mapWithKeys(fn (CollectionContract $record): array => [$record->getKey() => $record->attr('name')])
                                        ->all();
                                }),

                            Forms\Components\MorphToSelect\Type::make(Product::modelClass())
                                ->titleAttribute('name.en')
                                ->label(__('lunarpanel::discount.relationmanagers.conditions.form.purchasable.types.product.label'))
                                ->getSearchResultsUsing(static function (Forms\Components\Select $component, string $search): array {
                                    return get_search_builder(Product::modelClass(), $search)
                                        ->get()
                                        ->mapWithKeys(fn (ProductContract $record): array => [$record->getKey() => $record->attr('name')])
                                        ->all();
                                }),

                            Forms\Components\MorphToSelect\Type::make(ProductVariant::modelClass())
                                ->titleAttribute('sku')
                                ->getSearchResultsUsing(static function (Forms\Components\Select $component, string $search): array {
                                    return get_search_builder(ProductVariant::modelClass(), $search)
                                        ->orWhere('sku', 'like', $search.'%')
                                        ->get()
                                        ->mapWithKeys(fn (ProductVariantContract $record): array => [$record->getKey() => $record->product->attr('name').' - '.$record->sku])
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
                ThumbnailImageColumn::make('discountable_id')
                    ->resolveThumbnailUrlUsing(fn (?Model $record) => $record?->discountable?->getThumbnailImage())
                    ->label(''),

                Tables\Columns\TextColumn::make('discountable.id')
                    ->label(
                        __('lunarpanel::discount.relationmanagers.conditions.table.name.label')
                    )
                    ->formatStateUsing(
                        fn (Model $record) => $record->discountable instanceof ProductVariantContract ? $record->discountable->product->attr('name').' - '.$record->discountable->sku : $record->discountable->attr('name')
                    ),

                Tables\Columns\TextColumn::make('discountable_type')
                    ->label(
                        __('lunarpanel::discount.relationmanagers.conditions.table.type.label')
                    )
                    ->formatStateUsing(
                        fn (Model $record) => str($record->discountable->morphName())->replace('_', ' ')->title(),
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
