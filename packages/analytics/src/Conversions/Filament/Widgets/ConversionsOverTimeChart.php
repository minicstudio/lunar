<?php

namespace Lunar\Analytics\Conversions\Filament\Widgets;

use Carbon\CarbonImmutable;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Lunar\Analytics\Conversions\Filament\Widgets\Concerns\InteractsWithConversionReport;

class ConversionsOverTimeChart extends ApexChartWidget
{
    use InteractsWithConversionReport;

    protected static ?string $chartId = 'conversionsOverTimeChart';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * "Funnel per day", "Funnel per week" or "Funnel per month".
     */
    protected function getHeading(): ?string
    {
        return __('lunarpanel.analytics::conversions.widgets.over_time.heading.'.$this->conversionReport()->overTimeInterval());
    }

    protected function getOptions(): array
    {
        $overTime = $this->conversionReport()->overTime();
        $labelFormat = $overTime['interval'] === 'month' ? 'MMM YYYY' : 'D MMM';

        $series = [];

        foreach ($overTime['steps'] as $step => $buckets) {
            $series[] = [
                'name' => __("lunarpanel.analytics::conversions.widgets.steps.{$step}"),
                'data' => array_values($buckets),
            ];
        }

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 320,
                'toolbar' => [
                    'show' => false,
                ],
            ],
            'series' => $series,
            'xaxis' => [
                'categories' => array_map(
                    fn (string $day): string => CarbonImmutable::parse($day)->locale(app()->getLocale())->isoFormat($labelFormat),
                    array_keys($overTime['steps']['add_to_cart']),
                ),
            ],
            'yaxis' => [
                'decimalsInFloat' => 0,
                'min' => 0,
            ],
            'stroke' => [
                'show' => true,
                'width' => 2,
                'colors' => ['transparent'],
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
        ];
    }
}
