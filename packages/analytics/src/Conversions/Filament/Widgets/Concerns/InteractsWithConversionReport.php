<?php

namespace Lunar\Analytics\Conversions\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Lunar\Analytics\Conversions\Filament\Pages\ConversionsPage;
use Lunar\Analytics\Conversions\Reports\ConversionReportQuery;

trait InteractsWithConversionReport
{
    use InteractsWithPageFilters;

    protected ?ConversionReportQuery $conversionReport = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $conversionReportFilters = null;

    /**
     * Only staff who may open the report see its widgets.
     */
    public static function canView(): bool
    {
        return ConversionsPage::canAccess();
    }

    /**
     * The report for the period chosen on the page.
     */
    protected function conversionReport(): ConversionReportQuery
    {
        if ($this->conversionReport === null || $this->conversionReportFilters !== $this->pageFilters) {
            [$from, $to] = ConversionsPage::periodFromFilters($this->pageFilters);

            $this->conversionReport = new ConversionReportQuery($from, $to);
            $this->conversionReportFilters = $this->pageFilters;
        }

        return $this->conversionReport;
    }

    /**
     * Format a rate (0–1) as a percentage, or "—" without a rate.
     */
    protected function formatRate(?float $rate): string
    {
        return $rate === null ? '—' : number_format($rate * 100, 1).'%';
    }
}
