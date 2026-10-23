<?php

namespace Lunar\Admin\Filament\Resources\OrderResource\Pages\Components;

use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Panel;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\EditProduct;
use Lunar\Admin\Livewire\Components\TableComponent;
use Lunar\Admin\Support\Concerns\CallsHooks;
use Lunar\Admin\Support\Tables\Components\KeyValue;
use Lunar\Models\ProductVariant;

class OrderItemsTable extends TableComponent
{
    use CallsHooks;

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
                            TextColumn::make('description')
                                ->url(function ($record) {
                                    if ($record->purchasable && $record->purchasable_type == ProductVariant::morphName()) {
                                        return EditProduct::getUrl(['record' => $record->purchasable->product_id]);
                                    }

                                    return null;
                                })
                                ->weight(FontWeight::Bold),
                            TextColumn::make('identifier')
                                ->color(Color::Gray),
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
            ->columns(static::getOrderLinesTableColumns());
    }

    public function table(Table $table): Table
    {
        return self::callStaticLunarHook('extendTable', $this->getDefaultTable($table));
    }
}
