<?php

namespace Lunar\Admin\Filament\Resources\ProductResource\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Support\Facades\FilamentIcon;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Admin\Events\ProductAssociationsUpdated;
use Lunar\Admin\Filament\Resources\ProductResource;
use Lunar\Admin\Support\Pages\BaseManageRelatedRecords;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Contracts\ProductAssociation as ProductAssociationContract;
use Lunar\Models\Product;
use Lunar\Models\ProductAssociation;

class ManageProductAssociations extends BaseManageRelatedRecords
{
    protected static string $resource = ProductResource::class;

    protected static string $relationship = 'associations';

    public static function getNavigationIcon(): ?string
    {
        return FilamentIcon::resolve('lunar::product-associations');
    }

    public function getTitle(): string
    {
        return __('lunarpanel::product.pages.associations.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('lunarpanel::product.pages.associations.label');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_target_id')
                    ->label(__('lunarpanel::product.pages.associations.form.target.label'))
                    ->required()
                    ->searchable(true)
                    ->getSearchResultsUsing(static function (Forms\Components\Select $component, string $search): array {
                        return get_search_builder(Product::modelClass(), $search)
                            ->get()
                            ->mapWithKeys(fn (ProductContract $record): array => [$record->getKey() => $record->translateAttribute('name')])
                            ->all();
                    }),
                Forms\Components\Select::make('type')
                    ->label(__('lunarpanel::product.pages.associations.form.type.label'))
                    ->required()
                    ->options([
                        ProductAssociation::ALTERNATE => __('lunarpanel::product.pages.associations.form.type.options.alternate'),
                        ProductAssociation::CROSS_SELL => __('lunarpanel::product.pages.associations.form.type.options.cross-sell'),
                        ProductAssociation::UP_SELL => __('lunarpanel::product.pages.associations.form.type.options.up-sell'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->inverseRelationship('parent')
            ->columns([
                Tables\Columns\TextColumn::make('target')
                    ->formatStateUsing(fn (ProductAssociationContract $record): string => $record->target->translateAttribute('name'))
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column, ProductAssociationContract $record): ?string {
                        $state = $column->getState();

                        if (strlen($record->target->translateAttribute('name')) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        // Only render the tooltip if the column contents exceeds the length limit.
                        return $record->target->translateAttribute('name');
                    })
                    ->label(__('lunarpanel::product.table.name.label')),
                Tables\Columns\TextColumn::make('target.variants.sku')
                    ->label(__('lunarpanel::product.table.sku.label')),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('lunarpanel::product.pages.associations.form.type.label'))
                    ->formatStateUsing(fn ($state) => __('lunarpanel::product.pages.associations.form.type.options.' . $state)),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->after(
                    fn () => ProductAssociationsUpdated::dispatch(
                        $this->getOwnerRecord()
                    )
                )
                ->label(__('lunarpanel::product.pages.associations.actions.create.label'))
                ->modalHeading(__('lunarpanel::product.pages.associations.actions.create.heading')),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->after(
                    fn () => ProductAssociationsUpdated::dispatch(
                        $this->getOwnerRecord()
                    )
                )
                ->modalHeading(__('lunarpanel::product.pages.associations.actions.delete.heading')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->after(
                        fn () => ProductAssociationsUpdated::dispatch(
                            $this->getOwnerRecord()
                        )
                    )
                    ->modalHeading(__('lunarpanel::product.pages.associations.actions.delete.bulk.heading')),
                ]),
            ]);
    }
}
