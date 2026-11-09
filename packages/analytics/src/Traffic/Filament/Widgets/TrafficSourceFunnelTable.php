<?php

namespace Lunar\Analytics\Traffic\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Lunar\Analytics\Traffic\Filament\Widgets\Concerns\InteractsWithTrafficReport;

/**
 * Per source and medium: visits, carts, orders, order value and order rate.
 * Orders without a source are the unknown row, last.
 */
class TrafficSourceFunnelTable extends TableWidget
{
    use InteractsWithTrafficReport;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('lunarpanel.analytics::traffic.widgets.funnel.heading'))
            ->records(fn (): array => collect($this->trafficReport()->funnel())
                ->mapWithKeys(fn (array $row): array => [$row['key'] => $row])
                ->all())
            ->columns([
                TextColumn::make('source')
                    ->label(__('lunarpanel.analytics::traffic.columns.source'))
                    ->placeholder(__('lunarpanel.analytics::traffic.unknown'))
                    ->weight('medium'),
                TextColumn::make('medium')
                    ->label(__('lunarpanel.analytics::traffic.columns.medium'))
                    ->placeholder('—'),
                TextColumn::make('visits')
                    ->label(__('lunarpanel.analytics::traffic.columns.visits'))
                    ->numeric()
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('carts')
                    ->label(__('lunarpanel.analytics::traffic.columns.carts'))
                    ->numeric()
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('orders')
                    ->label(__('lunarpanel.analytics::traffic.columns.orders'))
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('order_value')
                    ->label(__('lunarpanel.analytics::traffic.columns.order_value'))
                    ->formatStateUsing(fn ($state): string => $this->formatOrderValue((int) $state))
                    ->alignEnd(),
                TextColumn::make('order_rate')
                    ->label(__('lunarpanel.analytics::traffic.columns.order_rate'))
                    ->formatStateUsing(fn ($state): ?string => $this->formatOrderRate($state === null ? null : (float) $state))
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->emptyStateHeading(__('lunarpanel.analytics::traffic.no_data'))
            ->paginated(false);
    }
}
