<?php

namespace Lunar\Analytics\Conversions\Reports;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Lunar\Analytics\Conversions\Enums\ConversionEventType;
use Lunar\Analytics\Conversions\Models\ConversionEvent;
use Lunar\Analytics\Reports\CountedOrders;
use Lunar\Analytics\Reports\ReportPeriod;

class ConversionReportQuery
{
    /**
     * The step each event type counts in.
     */
    protected const STEP_OF_TYPE = [
        'add_to_cart' => 'add_to_cart',
        'cart_started' => 'carts',
        'begin_checkout' => 'checkouts',
    ];

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
     * The interval `overTime()` groups by for this period's length.
     *
     * @return 'day'|'week'|'month'
     */
    public function overTimeInterval(): string
    {
        return $this->period->interval();
    }

    /**
     * The period's adds to cart, carts, checkouts and purchases, and the rates
     * between them: checkouts per cart, purchases per checkout and purchases
     * per cart. A rate whose divisor is 0 is null.
     *
     * Purchases are all counted orders. The purchase rates divide only the
     * tracked purchases, whose cart has a conversion event of any time, as
     * the traffic report's rates divide only orders with a source.
     *
     * @return array{add_to_cart: int, carts: int, checkouts: int, purchases: int, tracked_purchases: int, checkout_rate: ?float, purchase_rate: ?float, cart_purchase_rate: ?float}
     */
    public function totals(): array
    {
        $events = $this->eventsQuery()
            ->selectRaw('type, COUNT(*) as events')
            ->groupBy('type')
            ->pluck('events', 'type');

        $addToCart = (int) ($events[ConversionEventType::AddToCart->value] ?? 0);
        $carts = (int) ($events[ConversionEventType::CartStarted->value] ?? 0);
        $checkouts = (int) ($events[ConversionEventType::BeginCheckout->value] ?? 0);
        $purchases = $this->orders->placedQuery()->count();
        $trackedPurchases = $purchases === 0 ? 0 : $this->trackedPurchasesQuery()->count();

        return [
            'add_to_cart' => $addToCart,
            'carts' => $carts,
            'checkouts' => $checkouts,
            'purchases' => $purchases,
            'tracked_purchases' => $trackedPurchases,
            'checkout_rate' => $this->rate($checkouts, $carts),
            'purchase_rate' => $this->rate($trackedPurchases, $checkouts),
            'cart_purchase_rate' => $this->rate($trackedPurchases, $carts),
        ];
    }

    /**
     * The four steps over time, grouped as `ReportPeriod::group()` does: per
     * day, week or month by period length; buckets without events or
     * purchases give 0. Purchases are only the tracked ones, as in the rates.
     *
     * @return array{interval: 'day'|'week'|'month', steps: array{add_to_cart: array<string, int>, carts: array<string, int>, checkouts: array<string, int>, tracked_purchases: array<string, int>}}
     */
    public function overTime(): array
    {
        $perDay = [
            'add_to_cart' => [],
            'carts' => [],
            'checkouts' => [],
            'tracked_purchases' => [],
        ];

        $events = $this->eventsQuery()
            ->selectRaw('type, DATE(created_at) as day, COUNT(*) as events')
            ->groupBy('type')
            ->groupByRaw('DATE(created_at)')
            ->get();

        foreach ($events as $row) {
            $step = self::STEP_OF_TYPE[(string) $row->type] ?? null;

            if ($step !== null) {
                $perDay[$step][(string) $row->day] = (int) $row->events;
            }
        }

        $perDay['tracked_purchases'] = $this->trackedPurchasesQuery()
            ->toBase()
            ->selectRaw('DATE(placed_at) as day, COUNT(*) as purchases')
            ->groupByRaw('DATE(placed_at)')
            ->pluck('purchases', 'day')
            ->map(fn (mixed $purchases): int => (int) $purchases)
            ->all();

        return [
            'interval' => $this->overTimeInterval(),
            'steps' => array_map(fn (array $days): array => $this->period->group($days), $perDay),
        ];
    }

    /**
     * The period's conversion events, without those of carts left out because of their orders.
     */
    protected function eventsQuery(): Builder
    {
        return ConversionEvent::query()
            ->toBase()
            ->whereBetween('created_at', [$this->period->from(), $this->period->to()])
            ->whereNotIn('cart_id', $this->orders->excludedCartIds());
    }

    /**
     * The period's counted orders whose cart has a conversion event of any time.
     */
    protected function trackedPurchasesQuery(): EloquentBuilder
    {
        return $this->orders->placedQuery()
            ->whereIn('cart_id', ConversionEvent::query()->select('cart_id'));
    }

    /**
     * A step divided by an earlier one, or null when the earlier one is 0.
     */
    protected function rate(int $count, int $divisor): ?float
    {
        return $divisor > 0 ? $count / $divisor : null;
    }
}
