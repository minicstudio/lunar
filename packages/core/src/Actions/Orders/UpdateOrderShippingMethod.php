<?php

namespace Lunar\Actions\Orders;

use Illuminate\Support\Collection;
use Lunar\Actions\AbstractAction;
use Lunar\DataTypes\ShippingOption;
use Lunar\Exceptions\Orders\InvalidOrderShippingMethodException;
use Lunar\Exceptions\Orders\LockedOrderProductLineEditException;
use Lunar\Exceptions\Orders\MissingOrderCartException;
use Lunar\Exceptions\Orders\UnauthorizedOrderProductLineEditException;
use Lunar\Facades\DB;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Order;

/**
 * Store one shipping method and recalculate the linked cart.
 *
 * The order-creation pipeline is not used. Stock, status, and payment stay as they are.
 * A saved shipping price is cleared so the chosen option is priced again.
 */
final class UpdateOrderShippingMethod extends AbstractAction
{
    private const OFFERED_OPTIONS_CACHE = 'lunar.order_shipping_method.offered_options';

    /**
     * Whether personal pickup became an address delivery.
     *
     * Checkout already stored the billing address as the shipping address.
     */
    public bool $changedPickupToAddressDelivery = false;

    public function execute(OrderContract $order, string $identifier): Order
    {
        $changedPickupToAddressDelivery = false;

        /** @var Order $saved */
        $saved = DB::transaction(function () use ($order, $identifier, &$changedPickupToAddressDelivery): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! UpdateOrderProductLines::userCanManageOrders()) {
                throw new UnauthorizedOrderProductLineEditException;
            }

            $gates = GetOrderProductLineEditGates::run($locked);

            if ($gates !== []) {
                throw new LockedOrderProductLineEditException($gates);
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
                throw new InvalidOrderShippingMethodException;
            }

            $line = $shippingLines->first();

            if (! filled($identifier)) {
                throw new InvalidOrderShippingMethodException;
            }

            if ((string) $line->identifier === $identifier) {
                throw new InvalidOrderShippingMethodException('shipping_method_unchanged');
            }

            $previousPoints = (int) ($this->metaValue($locked, 'loyalty_points_to_redeem') ?? 0);

            $recalculation = new RecalculateEditedOrder;
            $recalculation->syncCart($locked, $cart);
            $this->assignShippingOption($locked, $cart, $identifier);

            $options = $this->shippingOptions($cart);
            $personalPickup = $this->isPersonalPickup($locked, $options);

            $chosen = $options
                ->first(fn (ShippingOption $option): bool => $option->getIdentifier() === $identifier);

            if (! $chosen instanceof ShippingOption || $this->isLocker($chosen)) {
                throw new InvalidOrderShippingMethodException;
            }

            if ($personalPickup && ! $chosen->collect) {
                $changedPickupToAddressDelivery = true;
            }

            $this->setShippingType($locked, $cart, $chosen->collect ? 'pickup' : 'courier');

            $meta = $this->metaArray($line->meta);
            unset($meta['shipping_price_override']);

            $line->update([
                'identifier' => $identifier,
                'meta' => $meta,
            ]);

            $locked->unsetRelation('lines');

            ShippingManifest::clearOptions();
            $recalculation->recalculate($locked, $cart, $previousPoints, keepStoredLoyaltyRedemption: true);

            return $locked->refresh();
        });

        $this->changedPickupToAddressDelivery = $changedPickupToAddressDelivery;

        return $saved;
    }

    /**
     * Options checkout would offer for the stored shipping address, without lockers.
     *
     * The alignment is rolled back, so opening the list does not keep the rebuilt cart lines.
     * A later read for the same order in this request reuses that list.
     *
     * @return Collection<int, ShippingOption>
     */
    public function offeredOptions(OrderContract $order): Collection
    {
        /** @var array<int|string, Collection<int, ShippingOption>> $cache */
        $cache = app()->bound(self::OFFERED_OPTIONS_CACHE)
            ? app(self::OFFERED_OPTIONS_CACHE)
            : [];

        $key = $order->getKey();

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $cache[$key] = $this->resolveOfferedOptions($order);
        app()->instance(self::OFFERED_OPTIONS_CACHE, $cache);

        return $cache[$key];
    }

    /**
     * @return Collection<int, ShippingOption>
     */
    private function resolveOfferedOptions(OrderContract $order): Collection
    {
        if (! $order->cart_id) {
            return collect();
        }

        /** @var Collection<int, ShippingOption> $options */
        $options = collect();
        $level = DB::transactionLevel();
        DB::beginTransaction();

        try {
            /** @var Order|null $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

            /** @var Cart|null $cart */
            $cart = $locked?->cart_id
                ? Cart::query()->whereKey($locked->cart_id)->lockForUpdate()->first()
                : null;

            if ($locked && $cart) {
                $recalculation = new RecalculateEditedOrder;
                $recalculation->syncCart($locked, $cart);
                $options = $this->shippingOptions($cart);
            }
        } finally {
            DB::rollBack($level);
        }

        return $options
            ->reject(fn (ShippingOption $option): bool => $this->isLocker($option))
            ->values();
    }

    /**
     * A parcel locker needs a chosen machine, which this save does not collect.
     */
    private function isLocker(ShippingOption $option): bool
    {
        return str_ends_with(strtolower($option->getIdentifier()), '-locker');
    }

    /**
     * @param  Collection<int, ShippingOption>  $options
     */
    private function isPersonalPickup(Order $order, Collection $options): bool
    {
        if ($this->metaValue($order, 'shippingType') === 'pickup') {
            return true;
        }

        $identifier = $order->shippingLines()->value('identifier');

        if (! filled($identifier)) {
            return false;
        }

        $current = $options
            ->first(fn (ShippingOption $option): bool => $option->getIdentifier() === (string) $identifier);

        return $current instanceof ShippingOption && $current->collect;
    }

    private function assignShippingOption(Order $order, Cart $cart, string $identifier): void
    {
        $cart->unsetRelation('shippingAddress');
        $cartAddress = $cart->shippingAddress()->first();

        if ($cartAddress) {
            $cartAddress->update([
                'shipping_option' => $identifier,
            ]);
        }

        $order->unsetRelation('shippingAddress');
        $orderAddress = $order->shippingAddress()->first();

        if ($orderAddress && filled($orderAddress->shipping_option)) {
            $orderAddress->update([
                'shipping_option' => $identifier,
            ]);
        }

        $cart->unsetRelation('shippingAddress');
        $cart->unsetRelation('addresses');
        $order->unsetRelation('shippingAddress');
        $order->unsetRelation('addresses');
    }

    private function setShippingType(Order $order, Cart $cart, string $shippingType): void
    {
        $orderMeta = $this->metaArray($order->meta);
        $orderMeta['shippingType'] = $shippingType;
        $order->update([
            'meta' => $orderMeta,
        ]);

        $cartMeta = $this->metaArray($cart->meta);
        $cartMeta['shippingType'] = $shippingType;
        $cart->update([
            'meta' => $cartMeta,
        ]);
    }

    /**
     * @return Collection<int, ShippingOption>
     */
    private function shippingOptions(Cart $cart): Collection
    {
        ShippingManifest::clearOptions();
        $cart->unsetRelation('shippingAddress');
        $cart->unsetRelation('lines');

        (new RecalculateEditedOrder)->priceLines($cart);

        return ShippingManifest::getOptions($cart);
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
