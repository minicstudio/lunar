<?php

namespace Lunar\Admin\Filament\Widgets\Dashboard\Orders;

use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Lunar\Facades\DB;
use Lunar\Models\Currency;
use Lunar\Models\Order;

class OrdersByStatusChart extends ApexChartWidget
{
    use InteractsWithPageFilters;

    /**
     * Chart Id
     */
    protected static ?string $chartId = 'ordersByStatusChart';

    protected static ?string $pollingInterval = '60s';

    protected function getHeading(): ?string
    {
        return __('lunarpanel::widgets.dashboard.orders.orders_by_status.heading');
    }

    protected function getOrderQuery(\DateTime|CarbonInterface|null $from = null, \DateTime|CarbonInterface|null $to = null)
    {
        return Order::whereNotNull('placed_at')
            ->with(['currency'])
            ->whereBetween('placed_at', [
                $from,
                $to,
            ]);
    }

    protected function getOptions(): array
    {
        $currency = Currency::getDefault();
        $statuses = config('lunar.orders.statuses', []);
        
        $date = now()->settings([
            'monthOverflow' => false,
        ]);

        $from = $date->clone()->subYear();
        $to = $date->clone();

        $period = CarbonPeriod::create($from, '1 month', $to);

        // Get all orders grouped by month and status
        $results = $this->getOrderQuery($from, $to)
            ->select(
                DB::RAW('MAX(currency_code) as currency_code'),
                DB::RAW('COUNT(*) as order_count'),
                DB::RAW('SUM(sub_total) as sub_total'),
                DB::RAW('SUM(total) as total'),
                'status',
                DB::RAW(db_date('placed_at', '%M', 'month')),
                DB::RAW(db_date('placed_at', '%Y', 'year')),
                DB::RAW(db_date('placed_at', '%Y%m', 'monthstamp'))
            )->groupBy(
                'status',
                DB::RAW('month'),
                DB::RAW('year'),
                DB::RAW('monthstamp'),
                DB::RAW(db_date('placed_at', '%Y-%m'))
            )->orderBy(DB::RAW(db_date('placed_at', '%Y-%m')), 'asc')
            ->orderBy('status', 'asc')
            ->get();

        // Prepare labels (months)
        $labels = [];
        foreach ($period as $date) {
            $labels[] = $date->locale(app()->getLocale())->isoFormat('MMMM YYYY');
        }

        // Prepare series data for each status
        $seriesCount = [];
        $seriesValue = [];
        $statusColors = [];

        // Initialize series arrays for each status
        foreach ($statuses as $statusKey => $statusConfig) {
            $seriesCount[$statusKey] = [
                'name' => $statusConfig['label'] ?? ucfirst($statusKey),
                'data' => array_fill(0, count($labels), 0),
            ];
            $seriesValue[$statusKey] = [
                'name' => $statusConfig['label'] ?? ucfirst($statusKey),
                'data' => array_fill(0, count($labels), 0),
            ];
            $statusColors[$statusKey] = $statusConfig['color'] ?? '#848a8c';
        }

        // Fill in the data from query results
        $monthIndex = 0;
        foreach ($period as $periodDate) {
            $monthstamp = $periodDate->format('Ym');
            
            // Get all results for this month
            $monthResults = $results->filter(function ($result) use ($monthstamp) {
                return $result->monthstamp == $monthstamp;
            });

            foreach ($monthResults as $result) {
                $status = $result->status;
                
                if (isset($seriesCount[$status])) {
                    // Order count
                    $seriesCount[$status]['data'][$monthIndex] = (int) $result->order_count;
                    
                    // Order value (sub_total) - stored for potential future use
                    // Currently showing count only
                }
            }
            
            $monthIndex++;
        }

        // Filter out statuses with no data
        $seriesCount = array_filter($seriesCount, function ($series) {
            return array_sum($series['data']) > 0;
        });
        
        $seriesValue = array_filter($seriesValue, function ($series) {
            return array_sum($series['data']) > 0;
        });

        // Build colors array matching the series order
        $colors = [];
        foreach (array_keys($seriesCount) as $statusKey) {
            $colors[] = $statusColors[$statusKey] ?? '#848a8c';
        }

        return [
            'chart' => [
                'type' => 'bar',
                'stacked' => true,
                'toolbar' => [
                    'show' => false,
                ],
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'series' => array_values($seriesCount),
            'xaxis' => [
                'categories' => $labels,
            ],
            'yaxis' => [
                [
                    'title' => [
                        'text' => __('lunarpanel::widgets.dashboard.orders.orders_by_status.yaxis.count.label'),
                    ],
                    'decimalsInFloat' => 0,
                ],
            ],
            'colors' => $colors,
            'legend' => [
                'position' => 'right',
                'horizontalAlign' => 'center',
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => "function(val) { return val + ' ' + '" . __('lunarpanel::widgets.dashboard.orders.orders_by_status.tooltip.orders') . "'; }",
                ],
            ],
            'plotOptions' => [
                'bar' => [
                    'horizontal' => false,
                    'dataLabels' => [
                        'total' => [
                            'enabled' => true,
                            'offsetX' => 0,
                            'offsetY' => 0,
                            'style' => [
                                'color' => '#ffffff',
                                'fontSize' => '13px',
                                'fontWeight' => 600,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}

