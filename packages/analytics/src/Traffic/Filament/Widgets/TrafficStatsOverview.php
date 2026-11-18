<?php

namespace Lunar\Analytics\Traffic\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;
use Lunar\Analytics\Traffic\Filament\Widgets\Concerns\InteractsWithTrafficReport;

/**
 * Visits, carts with a source, orders, order value, order rate and cart order rate for the period.
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
                ->description($this->orderRateDescription()),
            Stat::make(__('lunarpanel.analytics::traffic.stats.cart_order_rate'), $this->formatOrderRate($totals['cart_order_rate']) ?? '—')
                ->description(__('lunarpanel.analytics::traffic.stats.cart_order_rate_description')),
        ];
    }

    /**
     * The order rate description, with the crawler note set apart below it
     * by a thin rule, in smaller text and with a "Note:" label.
     */
    protected function orderRateDescription(): HtmlString
    {
        return new HtmlString(sprintf(
            '%s<span style="display: block; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid color-mix(in srgb, currentColor 25%%, transparent); font-size: 0.75rem; line-height: 1rem;"><strong>%s:</strong> %s</span>',
            e(__('lunarpanel.analytics::traffic.stats.order_rate_description')),
            e(__('lunarpanel.analytics::traffic.stats.note')),
            e(__('lunarpanel.analytics::traffic.stats.order_rate_crawler_note')),
        ));
    }
}
