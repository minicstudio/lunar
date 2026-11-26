<?php

namespace Lunar\Analytics\Reports;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Collection;
use Lunar\Models\Order;

/**
 * The orders that count in the analytics reports for one period, and the
 * carts and visits left out because of their orders.
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
class CountedOrders
{
    /**
     * Status of an order that was split into new orders.
     */
    protected const SPLIT_STATUS = 'split';

    /**
     * Visits and carts left out because of their orders, and the split
     * originals that count; null until looked up.
     *
     * @var array{visit_ids: list<int>, cart_ids: list<int>, split_original_ids: list<int>}|null
     */
    protected ?array $orderExclusions = null;

    public function __construct(protected ReportPeriod $period) {}

    /**
     * Orders placed in the period that count.
     *
     * Orders split from another order are left out; their split original
     * counts in their place, by its own `placed_at`.
     *
     * @return EloquentBuilder<Order>
     */
    public function placedQuery(): EloquentBuilder
    {
        $splitOriginalIds = $this->orderExclusions()['split_original_ids'];

        return Order::query()
            ->whereNotNull('placed_at')
            ->whereBetween('placed_at', [$this->period->from(), $this->period->to()])
            ->where(fn (EloquentBuilder $query) => $query
                ->where(fn (EloquentBuilder $query) => $query
                    ->reportable()
                    ->whereNull('meta->split_from_order_id'))
                ->when($splitOriginalIds !== [], fn (EloquentBuilder $query) => $query
                    ->orWhereIn('id', $splitOriginalIds)));
    }

    /**
     * Ids of the carts left out because none of their orders counts.
     *
     * @return list<int>
     */
    public function excludedCartIds(): array
    {
        return $this->orderExclusions()['cart_ids'];
    }

    /**
     * Ids of the visits left out because none of their orders counts.
     *
     * @return list<int>
     */
    public function excludedVisitIds(): array
    {
        return $this->orderExclusions()['visit_ids'];
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
                ->where('created_at', '>=', $this->period->from())
                ->orWhere('placed_at', '>=', $this->period->from()))
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
}
