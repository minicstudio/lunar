<?php

namespace Lunar\Observers;

use Lunar\Exceptions\Orders\LockedOrderSplitException;
use Lunar\Jobs\Orders\MarkAsNewCustomer;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;

class OrderObserver
{
    /**
     * Handle the Order "updating" event.
     *
     * @return void
     */
    public function updating(OrderContract $order)
    {
        /** @var Order $order */
        if ($order->getOriginal('status') === 'split' && $order->status !== 'split') {
            throw new LockedOrderSplitException(['closed']);
        }

        if ($order->getOriginal('status') != $order->status) {
            activity()
                ->causedBy(auth()->user())
                ->performedOn($order)
                ->event('status-update')
                ->withProperties([
                    'new' => $order->status,
                    'previous' => $order->getOriginal('status'),
                ])->log('status-update');
        }
    }

    /**
     * Re-check the new/returning flag when the order is first placed.
     *
     * Creation can run before placed_at is set, and a missed run stays on the
     * default returning value. Placement is the moment a previous purchase can
     * be compared by placed_at.
     */
    public function updated(OrderContract $order): void
    {
        /** @var Order $order */
        if (! $order->wasChanged('placed_at') || filled($order->getOriginal('placed_at')) || blank($order->placed_at)) {
            return;
        }

        MarkAsNewCustomer::dispatch($order->id)->afterCommit();
    }
}
