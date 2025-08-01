<?php

namespace Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Product;

class ProductLimitationRelationManager extends BaseRelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'discountables';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function getDefaultTable(Table $table): Table
    {
        return $table
            ->heading(
                __('lunarpanel::discount.relationmanagers.products.title')
            )
            ->description(
                __('lunarpanel::discount.relationmanagers.products.description')
            )
            ->paginated(false)
            ->modifyQueryUsing(
                fn ($query) => $query->whereIn('type', ['limitation', 'exclusion'])
                    ->whereDiscountableType(Product::morphName())
                    ->whereHas('discountable')
            )
            ->headerActions([
                Tables\Actions\CreateAction::make()->form([
                    Forms\Components\MorphToSelect::make('discountable')
                        ->searchable(true)
                        ->label(
                            __('lunarpanel::discount.relationmanagers.products.form.purchasable.label')
                        )
                        ->types([
                            Forms\Components\MorphToSelect\Type::make(Product::modelClass())
                                ->label(__('lunarpanel::discount.relationmanagers.products.form.purchasable.types.product.label'))
                                ->titleAttribute('name.en')
                                ->getSearchResultsUsing(static function (Forms\Components\Select $component, string $search): array {
                                    return get_search_builder(Product::modelClass(), $search)
                                        ->get()
                                        ->mapWithKeys(fn (ProductContract $record): array => [$record->getKey() => $record->attr('name')])
                                        ->all();
                                }),
                        ]),
                ])->label(
                    __('lunarpanel::discount.relationmanagers.products.actions.attach.label')
                )
                ->modalHeading(
                    __('lunarpanel::discount.relationmanagers.products.actions.attach.modal.heading')
                )
                ->mutateFormDataUsing(function (array $data) {
                    $data['type'] = 'limitation';

                    return $data;
                }),
            ])->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('discountable.thumbnail')
                    ->collection(config('lunar.media.collection'))
                    ->conversion('small')
                    ->limit(1)
                    ->square()
                    ->label(''),
                Tables\Columns\TextColumn::make('discountable.attribute_data.name')
                    ->label(
                        __('lunarpanel::discount.relationmanagers.products.table.name.label')
                    )
                    ->formatStateUsing(
                        fn (Model $record) => $record->discountable->attr('name')
                    ),
            ])->actions([
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.products.actions.delete.heading')
                    ),
            ])->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.products.actions.delete.bulk.heading')
                    ),
            ]);
    }
}
