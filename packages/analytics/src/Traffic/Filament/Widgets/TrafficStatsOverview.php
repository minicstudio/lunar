<?php

namespace Lunar\Analytics\Traffic\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Lunar\Analytics\Traffic\Filament\Widgets\Concerns\InteractsWithTrafficReport;

/**
 * Visits, carts with a source, orders, order value and order rate for the period.
 */
class TrafficStatsOverview extends StatsOverviewWidget
{
    use InteractsWithTrafficReport;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totals = $this->trafficReport()->totals();

        return [
            Stat::make(__('lunarpanel.analytics::traffic.stats.visits'), number_format($totals['visits'])),
            Stat::make(__('lunarpanel.analytics::traffic.stats.carts'), number_format($totals['carts']))
                ->description(__('lunarpanel.analytics::traffic.stats.carts_description')),
            Stat::make(__('lunarpanel.analytics::traffic.stats.orders'), number_format($totals['orders']))
                ->description(__('lunarpanel.analytics::traffic.stats.orders_description', [
                    'count' => number_format($totals['attributed_orders']),
                ])),
            Stat::make(__('lunarpanel.analytics::traffic.stats.order_value'), $this->formatOrderValue($totals['order_value'])),
            Stat::make(__('lunarpanel.analytics::traffic.stats.order_rate'), $this->formatOrderRate($totals['order_rate']) ?? '—')
                ->description(__('lunarpanel.analytics::traffic.stats.order_rate_description')),
        ];
    }
}
