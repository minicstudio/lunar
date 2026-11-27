<?php

namespace Lunar\Analytics\Conversions\Filament\Widgets;

use Filament\Support\RawJs;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Lunar\Analytics\Conversions\Filament\Widgets\Concerns\InteractsWithConversionReport;

class ConversionFunnelChart extends ApexChartWidget
{
    use InteractsWithConversionReport;

    /**
     * The funnel steps, from top to bottom.
     */
    protected const STEPS = ['add_to_cart', 'carts', 'checkouts', 'tracked_purchases'];

    protected static ?string $chartId = 'conversionFunnelChart';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('lunarpanel.analytics::conversions.widgets.funnel.heading');
    }

    /**
     * The period's adds to cart, carts, checkouts and purchases from tracked
     * carts as an ApexCharts funnel.
     */
    protected function getOptions(): array
    {
        $totals = $this->conversionReport()->totals();

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 280,
                'toolbar' => [
                    'show' => false,
                ],
            ],
            'series' => [
                [
                    'name' => '',
                    'data' => array_map(fn (string $step): int => $totals[$step], self::STEPS),
                ],
            ],
            'xaxis' => [
                'categories' => array_map(fn (string $step): string => __("lunarpanel.analytics::conversions.widgets.steps.{$step}"), self::STEPS),
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => true,
                    'isFunnel' => true,
                    'distributed' => true,
                    'borderRadius' => 0,
                    'barHeight' => '85%',
                    'dataLabels' => [
                        'hideOverflowingLabels' => false,
                    ],
                ],
                'funnel' => [
                    'shape' => 'trapezoid',
                ],
            ],
            'legend' => [
                'show' => false,
            ],
            'tooltip' => [
                'enabled' => false,
            ],
            'states' => [
                'hover' => ['filter' => ['type' => 'none']],
                'active' => ['filter' => ['type' => 'none']],
            ],
        ];
    }

    /**
     * Label each step with its name and count, as the funnel hides the axis labels.
     */
    protected function extraJsOptions(): ?RawJs
    {
        return RawJs::make(<<<'JS'
        {
            dataLabels: {
                enabled: true,
                formatter: function (value, options) {
                    return options.w.globals.labels[options.dataPointIndex] + ': ' + Number(value).toLocaleString('en-US')
                },
                dropShadow: {
                    enabled: true,
                },
            },
        }
        JS);
    }
}
