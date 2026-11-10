<?php

namespace Lunar\Actions\Orders;

use Lunar\Actions\AbstractAction;
use Lunar\Exceptions\Orders\InvalidOrderShippingPriceException;
use Lunar\Exceptions\Orders\LockedOrderProductLineEditException;
use Lunar\Exceptions\Orders\MissingOrderCartException;
use Lunar\Exceptions\Orders\UnauthorizedOrderProductLineEditException;
use Lunar\Facades\DB;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;

/**
 * Store one shipping price and recalculate the linked cart.
 *
 * The order-creation pipeline is not used. Stock, status, and payment stay as they are.
 */
final class UpdateOrderShippingPrice extends AbstractAction
{
    public function execute(OrderContract $order, int $amount): Order
    {
        /** @var Order $saved */
        $saved = DB::transaction(function () use ($order, $amount): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! UpdateOrderProductLines::userCanManageOrders()) {
                throw new UnauthorizedOrderProductLineEditException;
            }

            $gates = GetOrderProductLineEditGates::run($locked);

            if ($gates !== []) {
                throw new LockedOrderProductLineEditException($gates);
            }

            if ($amount < 0) {
                throw new InvalidOrderShippingPriceException;
            }

            if (! $locked->cart_id) {
                throw new MissingOrderCartException;
            }

            /** @var Cart|null $cart */
            $cart = Cart::query()->whereKey($locked->cart_id)->lockForUpdate()->first();

            if (! $cart) {
                throw new MissingOrderCartException;
            }

            $shippingLines = $locked->shippingLines()->get();

            if ($shippingLines->count() !== 1) {
                throw new InvalidOrderShippingPriceException;
            }

            $line = $shippingLines->first();
            $meta = $this->metaArray($line->meta);
            $meta['shipping_price_override'] = true;
            unset($meta['preorder_shipping_pending']);

            $line->update([
                'unit_price' => $amount,
                'meta' => $meta,
            ]);

            $previousPoints = (int) ($this->metaValue($locked, 'loyalty_points_to_redeem') ?? 0);

            $recalculation = new RecalculateEditedOrder;
            $recalculation->syncCart($locked, $cart);
            $recalculation->recalculate($locked, $cart, $previousPoints, keepStoredLoyaltyRedemption: true);

            return $locked->refresh();
        });

        return $saved;
    }

    /**
     * @return array<string, mixed>
     */
    private function metaArray(mixed $meta): array
    {
        if ($meta instanceof \ArrayObject) {
            return $meta->getArrayCopy();
        }

        if (is_array($meta)) {
            return $meta;
        }

        return [];
    }

    private function metaValue(Order $order, string $key): mixed
    {
        if ($order->meta === null || ! isset($order->meta[$key])) {
            return null;
        }

        return $order->meta[$key];
    }
}
