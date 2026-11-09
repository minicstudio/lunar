<?php

namespace Lunar\Analytics\Traffic\Filament\Widgets;

use Carbon\CarbonImmutable;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Lunar\Analytics\Traffic\Filament\Widgets\Concerns\InteractsWithTrafficReport;

/**
 * Line of the period's visits per day, week or month, by period length;
 * days, weeks and months without visits show 0.
 */
class VisitsOverTimeChart extends ApexChartWidget
{
    use InteractsWithTrafficReport;

    protected static ?string $chartId = 'trafficVisitsOverTimeChart';

    protected ?string $pollingInterval = null;

    /**
     * "Visits per day", "Visits per week" or "Visits per month".
     */
    protected function getHeading(): ?string
    {
        return __('lunarpanel.analytics::traffic.widgets.visits_over_time.heading.'.$this->trafficReport()->visitsOverTimeInterval());
    }

    protected function getOptions(): array
    {
        $visitsOverTime = $this->trafficReport()->visitsOverTime();
        $labelFormat = $visitsOverTime['interval'] === 'month' ? 'MMM YYYY' : 'D MMM';

        return [
            'chart' => [
                'type' => 'line',
                'height' => 320,
                'toolbar' => [
                    'show' => false,
                ],
            ],
            'series' => [
                [
                    'name' => __('lunarpanel.analytics::traffic.widgets.visits_over_time.series'),
                    'data' => array_values($visitsOverTime['visits']),
                ],
            ],
            'xaxis' => [
                'categories' => array_map(
                    fn (string $day): string => CarbonImmutable::parse($day)->locale(app()->getLocale())->isoFormat($labelFormat),
                    array_keys($visitsOverTime['visits']),
                ),
            ],
            'yaxis' => [
                'decimalsInFloat' => 0,
                'min' => 0,
            ],
            'stroke' => [
                'curve' => 'smooth',
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
        ];
    }
}
