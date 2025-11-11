<?php

namespace Lunar\Admin\Filament\Widgets\Dashboard\Orders;

use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Lunar\Facades\DB;
use Lunar\Models\Currency;
use Lunar\Models\Order;

class OrderValuesByStatusChart extends ApexChartWidget
{
    use InteractsWithPageFilters;

    /**
     * Chart Id
     */
    protected static ?string $chartId = 'orderValuesByStatusChart';

    protected static ?string $pollingInterval = '60s';

    protected function getHeading(): ?string
    {
        return __('lunarpanel::widgets.dashboard.orders.order_values_by_status.heading');
    }

    protected function getSubheading(): ?string
    {
        return __('lunarpanel::widgets.dashboard.orders.order_values_by_status.description');
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
        // Select total and shipping_total separately so Lunar can cast them to Money objects
        // Then calculate total - shipping_total in PHP to get order value without shipping costs
        $results = $this->getOrderQuery($from, $to)
            ->select(
                DB::RAW('MAX(currency_code) as currency_code'),
                DB::RAW('SUM(total) as total'),
                DB::RAW('SUM(shipping_total) as shipping_total'),
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

        // Debug: Check if we're getting all statuses including rejected
        // Uncomment to debug: \Log::info('OrderValuesByStatusChart Results', ['count' => $results->count(), 'statuses' => $results->pluck('status')->unique()->values()->toArray()]);

        // Prepare labels (months)
        $labels = [];
        foreach ($period as $date) {
            $labels[] = $date->locale(app()->getLocale())->isoFormat('MMMM YYYY');
        }

        // Prepare series data for each status
        $seriesValue = [];
        $statusColors = [];

        // Initialize series arrays for each status
        foreach ($statuses as $statusKey => $statusConfig) {
            $seriesValue[$statusKey] = [
                'name' => $statusConfig['label'] ?? ucfirst($statusKey),
                'data' => array_fill(0, count($labels), 0),
            ];
            $statusColors[$statusKey] = $statusConfig['color'] ?? '#848a8c';
        }

        // Fill in the data from query results - following OrderTotalsChart pattern
        $monthIndex = 0;
        foreach ($period as $periodDate) {
            $monthstamp = $periodDate->format('Ym');
            
            // Get all results for this month
            $monthResults = $results->filter(function ($result) use ($monthstamp) {
                return $result->monthstamp == $monthstamp;
            });

            foreach ($monthResults as $result) {
                $status = $result->status;
                
                if (isset($seriesValue[$status])) {
                    // Calculate order value: total - shipping_total (order value without shipping)
                    // Lunar automatically casts SUM results to Money objects when currency_code is present
                    // Since we group by status and month, there should be only one result per status per month
                    try {
                        $totalValue = $result->total && is_object($result->total) && method_exists($result->total, 'decimal')
                            ? (float) $result->total->decimal
                            : 0;
                        $shippingValue = $result->shipping_total && is_object($result->shipping_total) && method_exists($result->shipping_total, 'decimal')
                            ? (float) $result->shipping_total->decimal
                            : 0;
                        // Round to whole number to avoid floating point precision issues
                        $seriesValue[$status]['data'][$monthIndex] = round($totalValue - $shippingValue);
                    } catch (\Exception $e) {
                        // Fallback if Money object access fails
                        $seriesValue[$status]['data'][$monthIndex] = 0;
                    }
                }
            }
            
            $monthIndex++;
        }

        // Filter out statuses with no data
        $seriesValue = array_filter($seriesValue, function ($series) {
            return array_sum($series['data']) > 0;
        });

        // Build colors array matching the series order and ensure data is properly formatted
        $colors = [];
        $seriesArray = [];
        
        foreach ($seriesValue as $statusKey => $series) {
            // Ensure all data values are numeric
            $series['data'] = array_map(function ($value) {
                return is_numeric($value) ? (float) $value : 0.0;
            }, $series['data']);
            
            $seriesArray[] = $series;
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
            'series' => $seriesArray,
            'xaxis' => [
                'categories' => $labels,
            ],
            'yaxis' => [
                [
                    'title' => [
                        'text' => __('lunarpanel::widgets.dashboard.orders.order_values_by_status.yaxis.label'),
                    ],
                    'decimalsInFloat' => 2,
                ],
            ],
            'colors' => $colors,
            'legend' => [
                'position' => 'right',
                'horizontalAlign' => 'center',
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => "function(val) { return '" . $currency->code . " ' + val.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }",
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

