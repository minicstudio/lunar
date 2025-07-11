<?php

namespace Lunar\Shipping\Filament\Resources\ShippingExclusionListResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Product;

class ShippingExclusionRelationManager extends RelationManager
{
    protected static string $relationship = 'exclusions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('lunarpanel.shipping::relationmanagers.exclusions.title_plural');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\MorphToSelect::make('purchasable')
                    ->types([
                        Forms\Components\MorphToSelect\Type::make(Product::modelClass())
                            ->label(__('lunarpanel.shipping::relationmanagers.exclusions.form.purchasable.label'))
                            ->titleAttribute('name')
                            ->getOptionLabelUsing(
                                fn (Model $record) => $record->purchasable->attr('name')
                            )
                            ->getSearchResultsUsing(static function (Forms\Components\Select $component, string $search): array {
                                return get_search_builder(Product::modelClass(), $search)
                                    ->get()
                                    ->mapWithKeys(fn (ProductContract $record): array => [$record->getKey() => $record->translateAttribute('name')])
                                    ->all();
                            }),
                    ])
                    ->label(
                        __('lunarpanel.shipping::relationmanagers.exclusions.form.purchasable.label')
                    )
                    ->required()
                    ->searchable(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('purchasable.thumbnail')
                    ->collection(config('lunar.media.collection'))
                    ->conversion('small')
                    ->limit(1)
                    ->square()
                    ->label(''),
                Tables\Columns\TextColumn::make('purchasable')
                    ->formatStateUsing(
                        fn ($state) => $state->attr('name')
                    )
                    ->limit(50)
                    ->label(__('lunarpanel::product.table.name.label')),
                Tables\Columns\TextColumn::make('purchasable.variants.sku')
                    ->label(__('lunarpanel::product.table.sku.label'))
                    ->tooltip(function (Tables\Columns\TextColumn $column, $state): ?string {

                        $skus = collect($state);

                        if ($skus->count() <= $column->getListLimit()) {
                            return null;
                        }

                        if ($skus->count() > 30) {
                            $skus = $skus->slice(0, 30);
                        }

                        return $skus->implode(', ');
                    })
                    ->listWithLineBreaks()
                    ->limitList(1)
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->mutateFormDataUsing(function (array $data, RelationManager $livewire) {
                        return $data;
                    })
                    ->label(__('lunarpanel.shipping::relationmanagers.exclusions.actions.create.modal.heading'))
                    ->modalHeading(__('lunarpanel.shipping::relationmanagers.exclusions.actions.create.modal.heading')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->modalHeading(__('lunarpanel.shipping::relationmanagers.exclusions.actions.edit.modal.heading')),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(__('lunarpanel.shipping::relationmanagers.exclusions.actions.delete.modal.heading')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->modalHeading(__('lunarpanel.shipping::relationmanagers.exclusions.actions.delete.bulk.modal.heading')),
                ]),
            ]);
    }
}
