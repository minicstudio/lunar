<?php

namespace Lunar\Analytics\Traffic\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Lunar\Analytics\Traffic\Models\TrafficVisit;
use Lunar\Facades\DB;
use Lunar\Models\Cart;
use Lunar\Models\Order;

/**
 * Computes every figure of the traffic sources report for one period.
 *
 * Visits count by `created_at`, carts by `created_at` (only carts with a
 * traffic source) and orders by `placed_at` (only placed orders that count).
 * The funnel compares these totals per source and medium; it does not follow
 * individual visits. Order value is `total - shipping_total`, summed without
 * currency conversion, like the dashboard.
 *
 * An order counts when `Order::reportable()` keeps its current status, placed
 * or not. A `split` original counts when one of the orders it was split into
 * counts, following orders that were split again. A split counts as one
 * order, the original, with its own `placed_at` and value; its new orders
 * are not counted (unlike the dashboard, which counts the new orders).
 *
 * Carts whose orders (by `cart_id`) all do not count, and visits whose orders
 * (by `meta.traffic_source.visit_id`) all do not count, are left out of every
 * figure. Carts and visits without orders are counted. The lookup runs once
 * per instance and reads only orders with an excluded status created or
 * placed from the period start.
 */
class TrafficReportQuery
{
    /**
     * Funnel row key of orders without a traffic source.
     */
    public const UNKNOWN_KEY = 'unknown';

    /**
     * Status of an order that was split into new orders.
     */
    protected const SPLIT_STATUS = 'split';

    protected CarbonImmutable $from;

    protected CarbonImmutable $to;

    /**
     * Visits and carts left out because of their orders, and the split
     * originals that count; null until looked up.
     *
     * @var array{visit_ids: list<int>, cart_ids: list<int>, split_original_ids: list<int>}|null
     */
    protected ?array $orderExclusions = null;

    /**
     * @param  CarbonInterface  $from  First day of the period, included.
     * @param  CarbonInterface  $to  Last day of the period, included.
     */
    public function __construct(CarbonInterface $from, CarbonInterface $to)
    {
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $this->from = CarbonImmutable::instance($from)->startOfDay();
        $this->to = CarbonImmutable::instance($to)->endOfDay();
    }

    /**
     * The period's first moment.
     */
    public function from(): CarbonImmutable
    {
        return $this->from;
    }

    /**
     * The period's last moment.
     */
    public function to(): CarbonImmutable
    {
        return $this->to;
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

        foreach (CarbonPeriod::create($this->from->startOfDay(), '1 day', $this->to->startOfDay()) as $day) {
            $key = $day->format('Y-m-d');
            $days[$key] = (int) ($counts[$key] ?? 0);
        }

        return $days;
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
            ->whereBetween('created_at', [$this->from, $this->to])
            ->whereNotIn('id', $this->orderExclusions()['cart_ids'])
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
        $splitOriginalIds = $this->orderExclusions()['split_original_ids'];

        return Order::query()
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$this->from, $this->to])
            ->where(fn (EloquentBuilder $query) => $query
                ->where(fn (EloquentBuilder $query) => $query
                    ->reportable()
                    ->whereNull('meta->split_from_order_id'))
                ->when($splitOriginalIds !== [], fn (EloquentBuilder $query) => $query
                    ->orWhereIn('id', $splitOriginalIds)))
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
     * the order rate divides orders with a source by visits.
     *
     * @return array{visits: int, carts: int, orders: int, attributed_orders: int, order_value: int, order_rate: ?float}
     */
    public function totals(): array
    {
        $funnel = collect($this->funnel());
        $attributed = $funnel->where('key', '!=', self::UNKNOWN_KEY);
        $visits = (int) $attributed->sum('visits');
        $attributedOrders = (int) $attributed->sum('orders');

        return [
            'visits' => $visits,
            'carts' => (int) $attributed->sum('carts'),
            'orders' => (int) $funnel->sum('orders'),
            'attributed_orders' => $attributedOrders,
            'order_value' => (int) $funnel->sum('order_value'),
            'order_rate' => $this->rate($attributedOrders, $visits),
        ];
    }

    /**
     * The period's visits, without those left out because of their orders.
     */
    protected function visitsQuery(): Builder
    {
        return TrafficVisit::query()
            ->toBase()
            ->whereBetween('created_at', [$this->from, $this->to])
            ->whereNotIn('id', $this->orderExclusions()['visit_ids']);
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
     * Look up, once, the visits and carts left out because none of their
     * orders counts, and the split originals that count.
     *
     * Every order is created after its cart and visit, so the orders with an
     * excluded status created or placed from the period start cover every
     * order of the period's carts and visits and every split original placed
     * in the period. Their cart and visit ids are candidates, except those of
     * split originals that count. A candidate stays out unless another order
     * that counts has the same `cart_id` or `meta.traffic_source.visit_id`;
     * split new orders carry their original's visit id.
     *
     * @return array{visit_ids: list<int>, cart_ids: list<int>, split_original_ids: list<int>}
     */
    protected function orderExclusions(): array
    {
        if ($this->orderExclusions !== null) {
            return $this->orderExclusions;
        }

        $excludedOrders = Order::query()
            ->whereNot(fn (EloquentBuilder $query) => $query->reportable())
            ->where(fn (EloquentBuilder $query) => $query
                ->where('created_at', '>=', $this->from)
                ->orWhere('placed_at', '>=', $this->from))
            ->get(['id', 'cart_id', 'status', 'meta']);

        $splitOriginalIds = $this->countingSplitOriginalIds($excludedOrders);

        [$countingOrders, $notCountingOrders] = $excludedOrders
            ->partition(fn (Order $order): bool => in_array((int) $order->id, $splitOriginalIds, true));

        $candidateCartIds = $this->ids($notCountingOrders->pluck('cart_id'))
            ->diff($this->ids($countingOrders->pluck('cart_id')))
            ->values()
            ->all();

        $candidateVisitIds = $this->ids($notCountingOrders->map(fn (Order $order): mixed => $this->visitIdOf($order)))
            ->diff($this->ids($countingOrders->map(fn (Order $order): mixed => $this->visitIdOf($order))))
            ->values()
            ->all();

        $keptCartIds = $candidateCartIds === [] ? [] : $this->ids(
            Order::reportable()
                ->whereIn('cart_id', $candidateCartIds)
                ->distinct()
                ->pluck('cart_id')
        )->all();

        $keptVisitIds = $candidateVisitIds === [] ? [] : $this->ids(
            Order::reportable()
                ->whereIn('meta->traffic_source->visit_id', $candidateVisitIds)
                ->toBase()
                ->select('meta->traffic_source->visit_id as visit_id')
                ->distinct()
                ->pluck('visit_id')
        )->all();

        return $this->orderExclusions = [
            'visit_ids' => array_values(array_diff($candidateVisitIds, $keptVisitIds)),
            'cart_ids' => array_values(array_diff($candidateCartIds, $keptCartIds)),
            'split_original_ids' => $splitOriginalIds,
        ];
    }

    /**
     * Ids of the split originals among the given orders that count.
     *
     * An original is a `split` order not split from another order. It counts
     * when one of its new orders counts, or is itself split and has a new
     * order that counts, and so on. One query per split level.
     *
     * @param  Collection<int, Order>  $orders
     * @return list<int>
     */
    protected function countingSplitOriginalIds(Collection $orders): array
    {
        /** @var array<int, int> $pending new order id => original id */
        $pending = [];

        foreach ($orders as $order) {
            if ($order->status !== self::SPLIT_STATUS || data_get($order->meta, 'split_from_order_id') !== null) {
                continue;
            }

            foreach ($this->splitIntoOrderIdsOf($order) as $newOrderId) {
                $pending[$newOrderId] = (int) $order->id;
            }
        }

        /** @var array<int, int> $counting */
        $counting = [];
        $seen = [];

        while ($pending !== []) {
            $seen += $pending;
            $next = [];

            $newOrders = Order::query()
                ->whereIn('id', array_keys($pending))
                ->where(fn (EloquentBuilder $query) => $query
                    ->reportable()
                    ->orWhere('status', self::SPLIT_STATUS))
                ->get(['id', 'status', 'meta']);

            foreach ($newOrders as $newOrder) {
                $originalId = $pending[(int) $newOrder->id];

                if ($newOrder->status !== self::SPLIT_STATUS) {
                    $counting[$originalId] = $originalId;

                    continue;
                }

                foreach ($this->splitIntoOrderIdsOf($newOrder) as $newOrderId) {
                    if (! isset($seen[$newOrderId])) {
                        $next[$newOrderId] = $originalId;
                    }
                }
            }

            $pending = array_filter($next, fn (int $originalId): bool => ! isset($counting[$originalId]));
        }

        return array_values($counting);
    }

    /**
     * The ids in an order's `meta.split_into_order_ids`.
     *
     * @return list<int>
     */
    protected function splitIntoOrderIdsOf(Order $order): array
    {
        $ids = data_get($order->meta, 'split_into_order_ids');

        return is_array($ids) ? $this->ids(collect($ids))->all() : [];
    }

    /**
     * The visit id in an order's `meta.traffic_source`.
     */
    protected function visitIdOf(Order $order): mixed
    {
        return data_get($order->meta, 'traffic_source.visit_id');
    }

    /**
     * Distinct positive integer ids from the given values.
     *
     * @param  Collection<array-key, mixed>  $values
     * @return Collection<int, int>
     */
    protected function ids(Collection $values): Collection
    {
        return $values
            ->toBase()
            ->filter(fn (mixed $value): bool => is_numeric($value) && (int) $value > 0)
            ->map(fn (mixed $value): int => (int) $value)
            ->unique()
            ->values();
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
     * Orders divided by visits, or null without visits.
     */
    protected function rate(int $orders, int $visits): ?float
    {
        return $visits > 0 ? $orders / $visits : null;
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
