<?php

namespace Lunar\Analytics\Traffic\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Lunar\Analytics\Reports\CountedOrders;
use Lunar\Analytics\Reports\ReportPeriod;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Facades\DB;
use Lunar\Models\Cart;

/**
 * Computes every figure of the traffic sources report for one period.
 *
 * Visits count by `created_at`, carts by `created_at` (only carts with a
 * traffic source) and orders by `placed_at` (only placed orders that count).
 * The funnel compares these totals per source and medium; it does not follow
 * individual visits. Order value is `total - shipping_total`, summed without
 * currency conversion, like the dashboard.
 *
 * Which orders count, and which carts and visits are left out because of
 * their orders, follows `CountedOrders`.
 */
class TrafficReportQuery
{
    /**
     * Funnel row key of orders without a traffic source.
     */
    public const UNKNOWN_KEY = 'unknown';

    /**
     * Longest period, in days, whose visits over time are shown per day.
     */
    public const DAILY_MAX_DAYS = ReportPeriod::DAILY_MAX_DAYS;

    /**
     * Longest period, in days, whose visits over time are shown per week.
     */
    public const WEEKLY_MAX_DAYS = ReportPeriod::WEEKLY_MAX_DAYS;

    protected ReportPeriod $period;

    protected CountedOrders $orders;

    /**
     * @param  CarbonInterface  $from  First day of the period, included.
     * @param  CarbonInterface  $to  Last day of the period, included.
     */
    public function __construct(CarbonInterface $from, CarbonInterface $to)
    {
        $this->period = new ReportPeriod($from, $to);
        $this->orders = new CountedOrders($this->period);
    }

    /**
     * The period's first moment.
     */
    public function from(): CarbonImmutable
    {
        return $this->period->from();
    }

    /**
     * The period's last moment.
     */
    public function to(): CarbonImmutable
    {
        return $this->period->to();
    }

    /**
     * Visits per source and medium, most visits first.
     *
     * @return list<array{source: string, medium: ?string, visits: int}>
     */
    public function visitsBySource(): array
    {
        return $this->visitsQuery()
            ->select('source', 'medium', DB::raw('COUNT(*) as visits'))
            ->groupBy('source', 'medium')
            ->orderByDesc('visits')
            ->orderBy('source')
            ->orderBy('medium')
            ->get()
            ->map(fn (object $row): array => [
                'source' => (string) $row->source,
                'medium' => $row->medium === null ? null : (string) $row->medium,
                'visits' => (int) $row->visits,
            ])
            ->values()
            ->all();
    }

    /**
     * Visits on each day of the period, keyed `Y-m-d`; days without visits give 0.
     *
     * @return array<string, int>
     */
    public function visitsPerDay(): array
    {
        $counts = $this->visitsQuery()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as visits')
            ->groupByRaw('DATE(created_at)')
            ->pluck('visits', 'day');

        $days = [];

        foreach ($this->period->days() as $day) {
            $days[$day] = (int) ($counts[$day] ?? 0);
        }

        return $days;
    }

    /**
     * Visits over time, grouped by period length as `ReportPeriod::group()`
     * does: per day for periods of up to 31 days, per week (Monday to Sunday)
     * up to 182 days, otherwise per calendar month; buckets without visits
     * give 0. Sums `visitsPerDay()`, without another query.
     *
     * @return array{interval: 'day'|'week'|'month', visits: array<string, int>}
     */
    public function visitsOverTime(): array
    {
        return [
            'interval' => $this->visitsOverTimeInterval(),
            'visits' => $this->period->group($this->visitsPerDay()),
        ];
    }

    /**
     * The interval `visitsOverTime()` groups by for this period's length.
     *
     * @return 'day'|'week'|'month'
     */
    public function visitsOverTimeInterval(): string
    {
        return $this->period->interval();
    }

    /**
     * Landing paths of the period's visits, most visited first.
     *
     * @return list<array{landing_path: string, visits: int}>
     */
    public function landingPages(?int $limit = null): array
    {
        return $this->landingPagesQuery()
            ->when($limit !== null, fn (Builder $query) => $query->limit($limit))
            ->get()
            ->map(fn (object $row): array => $this->landingPageRow($row))
            ->values()
            ->all();
    }

    /**
     * One page of landing paths, most visited first.
     *
     * @return LengthAwarePaginator<int, array{landing_path: string, visits: int}>
     */
    public function paginateLandingPages(int $perPage, int $page = 1): LengthAwarePaginator
    {
        $paginator = $this->landingPagesQuery()->paginate($perPage, ['*'], 'page', $page);

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $row): array => $this->landingPageRow($row))
        );

        return $paginator;
    }

    /**
     * Carts created in the period with a traffic source, per source and medium.
     *
     * Carts without a source are left out; they include admin-created carts,
     * for example from order splits. So are carts whose orders all do not count.
     *
     * @return list<array{source: string, medium: ?string, carts: int}>
     */
    public function cartsBySource(): array
    {
        return Cart::query()
            ->toBase()
            ->whereBetween('created_at', [$this->period->from(), $this->period->to()])
            ->whereNotIn('id', $this->orders->excludedCartIds())
            ->whereNotNull('meta->traffic_source->source')
            ->select([
                'meta->traffic_source->source as source',
                'meta->traffic_source->medium as medium',
                DB::raw('COUNT(*) as carts'),
            ])
            ->groupBy('meta->traffic_source->source', 'meta->traffic_source->medium')
            ->get()
            ->map(fn (object $row): array => [
                'source' => (string) $row->source,
                'medium' => $this->nullableString($row->medium),
                'carts' => (int) $row->carts,
            ])
            ->values()
            ->all();
    }

    /**
     * Orders placed in the period that count, per source and medium, with
     * count and order value. Orders without a source have a null source.
     *
     * Orders split from another order are left out; their split original
     * counts in their place, by its own `placed_at` and value.
     *
     * @return list<array{source: ?string, medium: ?string, orders: int, order_value: int}>
     */
    public function ordersBySource(): array
    {
        return $this->orders->placedQuery()
            ->toBase()
            ->select([
                'meta->traffic_source->source as source',
                'meta->traffic_source->medium as medium',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total - shipping_total) as order_value'),
            ])
            ->groupBy('meta->traffic_source->source', 'meta->traffic_source->medium')
            ->get()
            ->map(fn (object $row): array => [
                'source' => $this->nullableString($row->source),
                'medium' => $this->nullableString($row->medium),
                'orders' => (int) $row->orders,
                'order_value' => (int) round((float) $row->order_value),
            ])
            ->values()
            ->all();
    }

    /**
     * Visits, carts, orders, order value and order rate per source and medium.
     *
     * Rows are ordered by visits, then orders. Orders without a source form
     * the unknown row, last, with no visits, carts or order rate.
     *
     * @return list<array{key: string, source: ?string, medium: ?string, visits: ?int, carts: ?int, orders: int, order_value: int, order_rate: ?float}>
     */
    public function funnel(): array
    {
        $rows = [];

        $row = function (string $source, ?string $medium) use (&$rows): string {
            $key = $source."\0".($medium ?? '');

            $rows[$key] ??= [
                'key' => $key,
                'source' => $source,
                'medium' => $medium,
                'visits' => 0,
                'carts' => 0,
                'orders' => 0,
                'order_value' => 0,
                'order_rate' => null,
            ];

            return $key;
        };

        foreach ($this->visitsBySource() as $visits) {
            $rows[$row($visits['source'], $visits['medium'])]['visits'] += $visits['visits'];
        }

        foreach ($this->cartsBySource() as $carts) {
            $rows[$row($carts['source'], $carts['medium'])]['carts'] += $carts['carts'];
        }

        $unknown = null;

        foreach ($this->ordersBySource() as $orders) {
            if ($orders['source'] === null || $orders['source'] === '') {
                $unknown ??= [
                    'key' => self::UNKNOWN_KEY,
                    'source' => null,
                    'medium' => null,
                    'visits' => null,
                    'carts' => null,
                    'orders' => 0,
                    'order_value' => 0,
                    'order_rate' => null,
                ];
                $unknown['orders'] += $orders['orders'];
                $unknown['order_value'] += $orders['order_value'];

                continue;
            }

            $key = $row($orders['source'], $orders['medium']);
            $rows[$key]['orders'] += $orders['orders'];
            $rows[$key]['order_value'] += $orders['order_value'];
        }

        foreach ($rows as $key => $values) {
            $rows[$key]['order_rate'] = $this->rate($values['orders'], (int) $values['visits']);
        }

        uasort($rows, fn (array $a, array $b): int => [$b['visits'], $b['orders'], $a['source'], $a['medium'] ?? '']
            <=> [$a['visits'], $a['orders'], $b['source'], $b['medium'] ?? '']);

        $funnel = array_values($rows);

        if ($unknown !== null) {
            $funnel[] = $unknown;
        }

        return $funnel;
    }

    /**
     * Report totals. Orders and order value include orders without a source;
     * the order rate divides orders with a source by visits, and the cart
     * order rate divides them by carts with a source.
     *
     * @return array{visits: int, carts: int, orders: int, attributed_orders: int, order_value: int, order_rate: ?float, cart_order_rate: ?float}
     */
    public function totals(): array
    {
        $funnel = collect($this->funnel());
        $attributed = $funnel->where('key', '!=', self::UNKNOWN_KEY);
        $visits = (int) $attributed->sum('visits');
        $carts = (int) $attributed->sum('carts');
        $attributedOrders = (int) $attributed->sum('orders');

        return [
            'visits' => $visits,
            'carts' => $carts,
            'orders' => (int) $funnel->sum('orders'),
            'attributed_orders' => $attributedOrders,
            'order_value' => (int) $funnel->sum('order_value'),
            'order_rate' => $this->rate($attributedOrders, $visits),
            'cart_order_rate' => $this->rate($attributedOrders, $carts),
        ];
    }

    /**
     * The period's visits, without those left out because of their orders.
     */
    protected function visitsQuery(): Builder
    {
        return TrafficVisit::query()
            ->toBase()
            ->whereBetween('created_at', [$this->period->from(), $this->period->to()])
            ->whereNotIn('id', $this->orders->excludedVisitIds());
    }

    /**
     * Grouped landing path query, most visited first.
     */
    protected function landingPagesQuery(): Builder
    {
        return $this->visitsQuery()
            ->select('landing_path', DB::raw('COUNT(*) as visits'))
            ->groupBy('landing_path')
            ->orderByDesc('visits')
            ->orderBy('landing_path');
    }

    /**
     * @return array{landing_path: string, visits: int}
     */
    protected function landingPageRow(object $row): array
    {
        return [
            'landing_path' => (string) $row->landing_path,
            'visits' => (int) $row->visits,
        ];
    }

    /**
     * Orders divided by visits or carts, or null when there are none.
     */
    protected function rate(int $orders, int $visitsOrCarts): ?float
    {
        return $visitsOrCarts > 0 ? $orders / $visitsOrCarts : null;
    }

    /**
     * A non-empty string, or null.
     */
    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' || $value === 'null' ? null : $value;
    }
}
