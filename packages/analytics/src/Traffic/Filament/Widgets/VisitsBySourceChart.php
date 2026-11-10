<?php

namespace Lunar\Analytics\Traffic\Filament\Widgets;

use Illuminate\Support\Str;
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
     * Number of sources shown before the rest are grouped as "other", one per color.
     */
    public const TOP_SOURCES = 8;

    /**
     * Slice colors of the top sources, in order. Each keeps 3:1 contrast on the
     * light and the dark panel, and neighbours stay apart for colour-blind eyes.
     */
    public const COLORS = ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'];

    /**
     * Slice color of "other": the neutral gray of the admin dashboard charts.
     */
    public const OTHER_COLOR = '#848a8c';

    /**
     * Characters of a label shown in the legend and tooltip; longer labels are cut.
     */
    public const LABEL_LENGTH = 40;

    protected static ?string $chartId = 'trafficVisitsBySourceChart';

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('lunarpanel.analytics::traffic.widgets.visits_by_source.heading');
    }

    /**
     * Labels, visit counts and colors: the top sources, then "other" when more exist.
     *
     * Labels are HTML-escaped: ApexCharts writes legend and tooltip labels as
     * HTML, and a source is free text from the visitor (`utm_source`, referrer).
     *
     * @return array{labels: list<string>, series: list<int>, colors: list<string>}
     */
    public function chartData(): array
    {
        $rows = collect($this->trafficReport()->visitsBySource());
        $top = $rows->take(self::TOP_SOURCES);
        $other = (int) $rows->slice(self::TOP_SOURCES)->sum('visits');

        $labels = $top->map(fn (array $row): string => e(Str::limit(TrafficSourceLabel::for($row)->label(), self::LABEL_LENGTH, '…')))->values()->all();
        $series = $top->pluck('visits')->map(fn ($visits): int => (int) $visits)->values()->all();
        $colors = array_slice(self::COLORS, 0, count($series));

        if ($other > 0) {
            $labels[] = e(__('lunarpanel.analytics::traffic.other'));
            $series[] = $other;
            $colors[] = self::OTHER_COLOR;
        }

        return ['labels' => $labels, 'series' => $series, 'colors' => $colors];
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
            'colors' => $data['colors'],
            'legend' => [
                'position' => 'bottom',
            ],
            'noData' => [
                'text' => __('lunarpanel.analytics::traffic.no_data'),
            ],
        ];
    }
}
