<?php

namespace Lunar\Analytics\Traffic\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Lunar\Analytics\Traffic\Filament\Pages\TrafficSourcesPage;
use Lunar\Analytics\Traffic\Reports\TrafficReportQuery;
use Lunar\DataTypes\Price;
use Lunar\Models\Currency;

/**
 * Shared by the traffic report widgets: the report for the page's period,
 * access, and value formatting.
 */
trait InteractsWithTrafficReport
{
    use InteractsWithPageFilters;

    protected ?TrafficReportQuery $trafficReport = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $trafficReportFilters = null;

    /**
     * Only staff who may open the report see its widgets.
     */
    public static function canView(): bool
    {
        return TrafficSourcesPage::canAccess();
    }

    /**
     * The report for the period chosen on the page.
     */
    protected function trafficReport(): TrafficReportQuery
    {
        if ($this->trafficReport === null || $this->trafficReportFilters !== $this->pageFilters) {
            [$from, $to] = TrafficSourcesPage::periodFromFilters($this->pageFilters);

            $this->trafficReport = new TrafficReportQuery($from, $to);
            $this->trafficReportFilters = $this->pageFilters;
        }

        return $this->trafficReport;
    }

    /**
     * Format an order value (minor units) in the default currency.
     */
    protected function formatOrderValue(int $value): string
    {
        $currency = Currency::getDefault();

        if (! $currency) {
            return number_format($value / 100, 2);
        }

        return (string) (new Price($value, $currency, 1))->formatted();
    }

    /**
     * Format an order rate (0–1) as a percentage, or null without a rate.
     */
    protected function formatOrderRate(?float $rate): ?string
    {
        return $rate === null ? null : number_format($rate * 100, 1).'%';
    }
}
