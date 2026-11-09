<?php

namespace Lunar\Analytics\Traffic\Filament\Widgets;

use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Lunar\Analytics\Traffic\Filament\Widgets\Concerns\InteractsWithTrafficReport;
use Lunar\Analytics\Traffic\Support\TrafficSourceLabel;

/**
 * Donut of the period's visits: the top sources plus "other", labeled as
 * the order badge names them ("Google · Paid ad").
 */
class VisitsBySourceChart extends ApexChartWidget
{
    use InteractsWithTrafficReport;

    /**
     * Number of sources shown before the rest are grouped as "other".
     */
    public const TOP_SOURCES = 8;

    protected static ?string $chartId = 'trafficVisitsBySourceChart';

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('lunarpanel.analytics::traffic.widgets.visits_by_source.heading');
    }

    /**
     * Labels and visit counts: the top sources, then "other" when more exist.
     *
     * @return array{labels: list<string>, series: list<int>}
     */
    public function chartData(): array
    {
        $rows = collect($this->trafficReport()->visitsBySource());
        $top = $rows->take(self::TOP_SOURCES);
        $other = (int) $rows->slice(self::TOP_SOURCES)->sum('visits');

        $labels = $top->map(fn (array $row): string => TrafficSourceLabel::for($row)->label())->values()->all();
        $series = $top->pluck('visits')->map(fn ($visits): int => (int) $visits)->values()->all();

        if ($other > 0) {
            $labels[] = __('lunarpanel.analytics::traffic.other');
            $series[] = $other;
        }

        return ['labels' => $labels, 'series' => $series];
    }

    protected function getOptions(): array
    {
        $data = $this->chartData();

        return [
            'chart' => [
                'type' => 'donut',
                'height' => 320,
            ],
            'series' => $data['series'],
            'labels' => $data['labels'],
            'legend' => [
                'position' => 'bottom',
            ],
            'noData' => [
                'text' => __('lunarpanel.analytics::traffic.no_data'),
            ],
        ];
    }
}
