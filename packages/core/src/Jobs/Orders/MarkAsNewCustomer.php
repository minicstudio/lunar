<?php

namespace Lunar\Jobs\Orders;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lunar\Facades\DB;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

class MarkAsNewCustomer implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected int $orderId) {}

    /**
     * Mark the order from its billing email and earlier placed orders.
     *
     * Only placed orders count. A draft with the same email is not a previous
     * purchase, and a blank email must not match every other blank address.
     * The column defaults to returning, so this job is the only thing that can
     * record a new customer.
     */
    public function handle(): void
    {
        DB::transaction(function () {
            $order = Order::find($this->orderId);

            if (! $order || ! $order->billingAddress) {
                return;
            }

            $email = $order->billingAddress->contact_email;

            if (blank($email)) {
                $order->new_customer = true;
                $order->saveQuietly();

                return;
            }

            $ordersTable = (new Order)->getTable();
            $addressesTable = (new OrderAddress)->getTable();
            $cutoff = $order->placed_at ?: $order->created_at;

            $hasPreviousOrder = OrderAddress::query()
                ->where("{$addressesTable}.order_id", '!=', $order->id)
                ->where("{$addressesTable}.type", 'billing')
                ->where("{$addressesTable}.contact_email", $email)
                ->join($ordersTable, "{$ordersTable}.id", '=', "{$addressesTable}.order_id")
                ->whereNotNull("{$ordersTable}.placed_at")
                ->where(function ($query) use ($ordersTable, $order, $cutoff) {
                    $query->where("{$ordersTable}.placed_at", '<', $cutoff)
                        ->orWhere(function ($query) use ($ordersTable, $order, $cutoff) {
                            $query->where("{$ordersTable}.placed_at", '=', $cutoff)
                                ->where("{$ordersTable}.id", '<', $order->id);
                        });
                })
                ->exists();

            $order->new_customer = ! $hasPreviousOrder;
            $order->saveQuietly();
        });
    }
}
