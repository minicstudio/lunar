<?php

namespace Lunar\Admin\Filament\Widgets\Dashboard\Orders;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Lunar\Facades\DB;
use Lunar\Models\OrderLine;

class PopularProductsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getTablePollingInterval(): ?string
    {
        return '60s';
    }

    public static function getHeading(): ?string
    {
        return __('lunarpanel::widgets.dashboard.latest_orders.heading');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(
                fn () => __('lunarpanel::widgets.dashboard.orders.popular_products.heading')
            )
            ->description(
                fn () => __('lunarpanel::widgets.dashboard.orders.popular_products.description')
            )
            ->query(function () {
                return OrderLine::query()->with(['currency'])->whereHas('order', function ($relation) {
                    $relation->whereBetween('placed_at', [
                        now()->subYear()->startOfDay(),
                        now()->endOfDay(),
                    ]);
                })->select(
                    DB::RAW('MAX(id) as id'),
                    DB::RAW('MAX(order_id) as order_id'),
                    DB::RAW('COUNT(id) as quantity'),
                    DB::RAW('SUM(sub_total) as sub_total'),
                    DB::RAW('MAX(description) as description'),
                    'identifier',
                )->groupBy('identifier', 'purchasable_id')
                    ->whereType('physical');
            })->defaultSort('quantity', 'desc')
            ->columns([
                TextColumn::make('description')
                    ->label(__('lunarpanel::widgets.dashboard.orders.popular_products.table.description')),
                TextColumn::make('identifier')
                    ->label(__('lunarpanel::widgets.dashboard.orders.popular_products.table.identifier')),
                TextColumn::make('quantity')
                    ->label(__('lunarpanel::widgets.dashboard.orders.popular_products.table.quantity')),                
                TextColumn::make('sub_total')
                    ->label(__('lunarpanel::widgets.dashboard.orders.popular_products.table.sub_total')),
            ]);
    }
}
