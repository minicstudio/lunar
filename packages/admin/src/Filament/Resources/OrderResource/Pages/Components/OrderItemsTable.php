<?php

namespace Lunar\Admin\Filament\Resources\OrderResource\Pages\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Lunar\Actions\Orders\GetOrderProductLineEditGates;
use Lunar\Actions\Orders\OrderProductLineChange;
use Lunar\Actions\Orders\UpdateOrderProductLines;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Livewire\Components\TableComponent;
use Lunar\Admin\Support\Concerns\CallsHooks;
use Lunar\Admin\Support\Orders\OrderProductLineEditFailure;
use Lunar\Admin\Support\Tables\Components\KeyValue;
use Lunar\Models\Contracts\Product as ProductContract;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Throwable;

class OrderItemsTable extends TableComponent
{
    use CallsHooks;

    /**
     * @var array<int, Collection<int, ProductVariant>>
     */
    protected array $variantsByProduct = [];

    public static function getOrderLinesTableColumns(): array
    {
        return self::callStaticLunarHook('extendOrderLinesTableColumns', [
            Split::make([
                ImageColumn::make('image')
                    ->defaultImageUrl(fn () => 'data:image/svg+xml;base64, '.base64_encode(
                        Blade::render('<x-filament::icon icon="heroicon-o-photo" style="color:rgb('.Color::Gray[400].');"/>')
                    ))
                    ->grow(false)
                    ->getStateUsing(fn ($record) => $record->purchasable?->getThumbnail()?->getUrl('small')),

                Stack::make([
                    Split::make([
                        Stack::make([
                            TextColumn::make('identifier')
                                ->color(Color::Gray),
                            TextColumn::make('description')
                                ->url(function ($record) {
                                    if ($record->purchasable && $record->purchasable_type == ProductVariant::morphName()) {
                                        return EditProduct::getUrl(['record' => $record->purchasable->product_id]);
                                    }

                                    return null;
                                })
                                ->description(function ($record): ?string {
                                    if ($record->purchasable_type != ProductVariant::morphName()) {
                                        return null;
                                    }

                                    if ($record->purchasable?->purchasable !== 'preorder') {
                                        return null;
                                    }

                                    return __('lunarpanel::order.infolist.preorder.message');
                                }),
                            TextColumn::make('options')
                                ->getStateUsing(fn ($record) => $record->purchasable?->getOptions())
                                ->badge(),
                        ]),
                        Stack::make([
                            TextColumn::make('unit')
                                ->alignEnd()
                                ->getStateUsing(fn ($record) => "{$record->quantity} @ {$record->sub_total->formatted}"),
                        ]),
                    ])
                        ->extraAttributes(['style' => 'align-items: start;']),
                ])
                    ->columnSpanFull(),
            ])->extraAttributes(['style' => 'align-items: start;']),
            Panel::make([
                Stack::make([
                    TextColumn::make('stock')
                        ->getStateUsing(fn ($record) => $record->purchasable?->stock)
                        ->formatStateUsing(fn ($state) => __('lunarpanel::order.infolist.current_stock_level.message', [
                            'count' => $state,
                        ]))
                        ->colors(fn () => [
                            'danger' => fn ($state) => $state < 50,
                            'success' => fn ($state) => $state >= 50,
                        ]),
                    TextColumn::make('meta.stock_level')
                        ->formatStateUsing(fn ($state) => __('lunarpanel::order.infolist.purchase_stock_level.message', [
                            'count' => $state,
                        ]))
                        ->color(Color::Gray),
                    TextColumn::make('notes')
                        ->description(new HtmlString('<b>'.__('lunarpanel::order.infolist.notes.label').'</b>'), 'above'),

                    KeyValue::make('price_breakdowns')
                        ->getStateUsing(function ($record) {

                            $states = [];

                            $states['unit_price'] = "{$record->unit_price->unitFormatted(decimalPlaces: 4)}";
                            $states['quantity'] = $record->quantity;
                            $states['sub_total'] = $record->sub_total?->formatted;
                            $states['discount_total'] = $record->discount_total?->formatted;

                            foreach ($record->tax_breakdown?->amounts ?? [] as $tax) {
                                $states[$tax->description] = $tax->price->formatted;
                            }

                            $states['total'] = $record->total?->formatted;

                            return $states;
                        }),
                ]),
            ])
                ->collapsed()
                ->collapsible(),
        ]);
    }

    public function getDefaultTable(Table $table): Table
    {
        return $table
            ->query($this->record->lines()->getQuery()
                ->with(['purchasable'])
                ->wherein('type', ['physical', 'digital']))
            ->columns(static::getOrderLinesTableColumns())
            ->headerActions([
                $this->getAddProductAction(),
            ])
            ->recordActions([
                $this->getUpdateQuantityAction(),
                $this->getRemoveProductAction(),
            ]);
    }

    public function table(Table $table): Table
    {
        return self::callStaticLunarHook('extendTable', $this->getDefaultTable($table));
    }

    protected function getAddProductAction(): Action
    {
        return Action::make('add_product')
            ->label(__('lunarpanel::order.action.add_product.label'))
            ->icon('heroicon-o-plus')
            ->schema([
                Select::make('product_id')
                    ->label(__('lunarpanel::order.action.add_product.product.label'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->getSearchResultsUsing(fn (string $search): array => $this->productSearchOptions($search))
                    ->getOptionLabelUsing(fn (mixed $value): ?string => $this->productOptionLabel($value))
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        $variants = $this->variantsForProduct($state);

                        $set('variant_id', $variants->count() === 1 ? $variants->first()->getKey() : null);
                    }),
                Select::make('variant_id')
                    ->label(__('lunarpanel::order.action.add_product.variant.label'))
                    ->native(false)
                    ->required()
                    ->visible(fn (Get $get): bool => $this->variantsForProduct($get('product_id'))->count() > 1)
                    ->options(fn (Get $get): array => $this->variantOptionsForProduct($get('product_id'))),
                TextInput::make('quantity')
                    ->label(__('lunarpanel::order.action.add_product.quantity.label'))
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->default(1),
            ])
            ->action(function (array $data, Action $action): void {
                $variant = $this->variantForAddedProduct($data);

                if ($variant === null) {
                    Notification::make()
                        ->title(__('lunarpanel::order.action.product_line_edit.purchasable'))
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $this->saveProductLineChange(OrderProductLineChange::add($variant, (int) $data['quantity']), $action);

                Notification::make()
                    ->title(__('lunarpanel::order.action.add_product.notification'))
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => $this->productLinesAreEditable());
    }

    protected function getUpdateQuantityAction(): Action
    {
        return Action::make('update_quantity')
            ->label(__('lunarpanel::order.action.update_quantity.label'))
            ->icon('heroicon-o-pencil')
            ->schema([
                TextInput::make('quantity')
                    ->label(__('lunarpanel::order.action.update_quantity.quantity.label'))
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->default(fn (OrderLine $record): int => $record->quantity),
            ])
            ->action(function (OrderLine $record, array $data, Action $action): void {
                $this->saveProductLineChange(OrderProductLineChange::setQuantity($record->id, (int) $data['quantity']), $action);

                Notification::make()
                    ->title(__('lunarpanel::order.action.update_quantity.notification'))
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => $this->productLinesAreEditable());
    }

    protected function getRemoveProductAction(): Action
    {
        return Action::make('remove_product')
            ->label(__('lunarpanel::order.action.remove_product.label'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('lunarpanel::order.action.remove_product.confirmation'))
            ->action(function (OrderLine $record, Action $action): void {
                $this->saveProductLineChange(OrderProductLineChange::remove($record->id), $action);

                Notification::make()
                    ->title(__('lunarpanel::order.action.remove_product.notification'))
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => $this->productLinesAreEditable());
    }

    /**
     * @return array<int, string>
     */
    protected function productSearchOptions(string $search): array
    {
        return get_search_builder(Product::modelClass(), $search)
            ->take(50)
            ->get()
            ->mapWithKeys(function (ProductContract $product): array {
                $name = $product->translateAttribute('name');

                return [
                    $product->getKey() => filled($name) ? $name : (string) $product->getKey(),
                ];
            })
            ->all();
    }

    protected function productOptionLabel(mixed $productId): ?string
    {
        if (blank($productId)) {
            return null;
        }

        $name = Product::modelClass()::query()->find($productId)?->translateAttribute('name');

        return filled($name) ? $name : null;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    protected function variantsForProduct(mixed $productId): Collection
    {
        if (blank($productId)) {
            return new Collection;
        }

        $productId = (int) $productId;

        return $this->variantsByProduct[$productId] ??= ProductVariant::query()
            ->with('values')
            ->where('product_id', $productId)
            ->orderBy('sku')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    protected function variantOptionsForProduct(mixed $productId): array
    {
        return $this->variantsForProduct($productId)
            ->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->getKey() => $this->variantOptionLabel($variant),
            ])
            ->all();
    }

    protected function variantOptionLabel(ProductVariant $variant): string
    {
        $options = $variant->getOption();
        $sku = $variant->sku;

        if (filled($options) && filled($sku)) {
            return $options.' - '.$sku;
        }

        if (filled($options)) {
            return $options;
        }

        return filled($sku) ? $sku : (string) $variant->getKey();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function variantForAddedProduct(array $data): ?ProductVariant
    {
        $variants = $this->variantsForProduct($data['product_id'] ?? null);

        if ($variants->count() === 1) {
            return $variants->first();
        }

        if (blank($data['variant_id'] ?? null)) {
            return null;
        }

        return $variants->first(
            fn (ProductVariant $variant): bool => (int) $variant->getKey() === (int) $data['variant_id'],
        );
    }

    protected function productLinesAreEditable(): bool
    {
        return UpdateOrderProductLines::userCanManageOrders()
            && GetOrderProductLineEditGates::run($this->orderRecord()) === [];
    }

    protected function saveProductLineChange(OrderProductLineChange $change, Action $action): void
    {
        try {
            UpdateOrderProductLines::run($this->orderRecord(), $change);
        } catch (Throwable $exception) {
            $title = OrderProductLineEditFailure::notificationTitle($exception);

            if ($title === null) {
                throw $exception;
            }

            Notification::make()
                ->title($title)
                ->danger()
                ->send();

            $action->halt();
        }

        $this->record = $this->record->fresh();
        $this->resetTable();
        $this->dispatch('order-product-lines-updated');
    }

    protected function orderRecord(): Order
    {
        /** @var Order $order */
        $order = $this->record;

        return $order;
    }
}
