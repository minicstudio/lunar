<?php

namespace Lunar\Analytics\Traffic\Filament\Widgets;

use Carbon\CarbonImmutable;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Lunar\Analytics\Traffic\Filament\Widgets\Concerns\InteractsWithTrafficReport;

/**
 * Line of visits on each day of the period; days without visits show 0.
 */
class VisitsPerDayChart extends ApexChartWidget
{
    use InteractsWithTrafficReport;

    protected static ?string $chartId = 'trafficVisitsPerDayChart';

    protected ?string $pollingInterval = null;

    protected function getHeading(): ?string
    {
        return __('lunarpanel.analytics::traffic.widgets.visits_per_day.heading');
    }

    protected function getOptions(): array
    {
        $days = $this->trafficReport()->visitsPerDay();

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
                    'name' => __('lunarpanel.analytics::traffic.widgets.visits_per_day.series'),
                    'data' => array_values($days),
                ],
            ],
            'xaxis' => [
                'categories' => array_map(
                    fn (string $day): string => CarbonImmutable::parse($day)->locale(app()->getLocale())->isoFormat('D MMM'),
                    array_keys($days),
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
