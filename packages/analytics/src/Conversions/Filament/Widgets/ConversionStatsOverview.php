<?php

namespace Lunar\Analytics\Conversions\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Lunar\Analytics\Conversions\Filament\Widgets\Concerns\InteractsWithConversionReport;

class ConversionStatsOverview extends StatsOverviewWidget
{
    use InteractsWithConversionReport;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totals = $this->conversionReport()->totals();

        return [
            Stat::make(__('lunarpanel.analytics::conversions.stats.add_to_cart'), number_format($totals['add_to_cart'])),
            Stat::make(__('lunarpanel.analytics::conversions.stats.carts'), number_format($totals['carts'])),
            Stat::make(__('lunarpanel.analytics::conversions.stats.checkouts'), number_format($totals['checkouts'])),
            Stat::make(__('lunarpanel.analytics::conversions.stats.purchases'), number_format($totals['purchases']))
                ->description(__('lunarpanel.analytics::conversions.stats.tracked_purchases', [
                    'count' => number_format($totals['tracked_purchases']),
                ])),
            Stat::make(__('lunarpanel.analytics::conversions.stats.checkout_rate'), $this->formatRate($totals['checkout_rate']))
                ->description(__('lunarpanel.analytics::conversions.stats.checkout_rate_description')),
            Stat::make(__('lunarpanel.analytics::conversions.stats.purchase_rate'), $this->formatRate($totals['purchase_rate']))
                ->description(__('lunarpanel.analytics::conversions.stats.purchase_rate_description')),
            Stat::make(__('lunarpanel.analytics::conversions.stats.cart_purchase_rate'), $this->formatRate($totals['cart_purchase_rate']))
                ->description(__('lunarpanel.analytics::conversions.stats.cart_purchase_rate_description')),
        ];
    }
}
