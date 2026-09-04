<?php

namespace Lunar\Admin\Filament\Resources\DiscountResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Admin\Events\BeforeDiscountLimitationAttached;
use Lunar\Admin\Events\DiscountLimitationAttached;
use Lunar\Admin\Events\DiscountLimitationBulkDetached;
use Lunar\Admin\Events\DiscountLimitationDetached;
use Lunar\Admin\Support\RelationManagers\BaseRelationManager;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Product;

class ProductLimitationRelationManager extends BaseRelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'discountables';

    protected array $attachedData = [];

    protected array $detachedData = [];

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
                CreateAction::make()->schema([
                    MorphToSelect::make('discountable')
                        ->searchable(true)
                        ->label(
                            __('lunarpanel::discount.relationmanagers.products.form.purchasable.label')
                        )
                        ->types([
                            Type::make(Product::modelClass())
                                ->label(__('lunarpanel::discount.relationmanagers.products.form.purchasable.types.product.label'))
                                ->titleAttribute('name.en')
                                ->getSearchResultsUsing(static function (Select $component, string $search): array {
                                    return get_search_builder(Product::modelClass(), $search)
                                        ->get()
                                        ->mapWithKeys(fn (ProductContract $record): array => [$record->getKey() => $record->attr('name')])
                                        ->all();
                                })
                                ->getOptionLabelUsing(function ($value): string {
                                    return Product::modelClass()::find($value)?->attr('name') ?? $value;
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
                    })
                    ->before(function ($record) {
                        BeforeDiscountLimitationAttached::dispatch($this->getOwnerRecord());
                    })
                    ->after(function ($record) {
                        $this->attachedData = [
                            'discount_id' => $this->getOwnerRecord()->id,
                            'discountable_id' => $record->discountable->id,
                            'discountable_type' => $record->discountable::class,
                        ];
                        DiscountLimitationAttached::dispatch($this->getOwnerRecord(), $this->attachedData);
                    }),
            ])->columns([
                SpatieMediaLibraryImageColumn::make('discountable.thumbnail')
                    ->collection(config('lunar.media.collection'))
                    ->conversion('small')
                    ->limit(1)
                    ->square()
                    ->label(''),
                TextColumn::make('discountable.attribute_data.name')
                    ->label(
                        __('lunarpanel::discount.relationmanagers.products.table.name.label')
                    )
                    ->formatStateUsing(
                        fn (Model $record) => $record->discountable->attr('name')
                    ),
            ])->recordActions([
                DeleteAction::make()
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.products.actions.delete.heading')
                    )
                    ->before(function ($record) {
                        $this->detachedData = [
                            'discount_id' => $this->getOwnerRecord()->id,
                            'discountable_id' => $record->discountable->id,
                            'discountable_type' => $record->discountable::class,
                        ];
                    })
                    ->after(function ($record) {
                        DiscountLimitationDetached::dispatch($this->getOwnerRecord(), $this->detachedData);
                    }),
            ])->toolbarActions([
                DeleteBulkAction::make()
                    ->modalHeading(
                        __('lunarpanel::discount.relationmanagers.products.actions.delete.bulk.heading')
                    )
                    ->after(function () {
                        DiscountLimitationBulkDetached::dispatch();
                    }),
            ]);
    }
}
