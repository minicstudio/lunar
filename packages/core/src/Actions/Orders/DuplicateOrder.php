<?php

namespace Lunar\Actions\Orders;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Collection;
use Lunar\Actions\AbstractAction;
use Lunar\Actions\Orders\DTOs\DuplicateOrderPreview;
use Lunar\Actions\Orders\DTOs\DuplicateOrderShippingMethod;
use Lunar\Base\DiscountManagerInterface;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Exceptions\Carts\CartException;
use Lunar\Exceptions\Orders\DuplicateOrderException;
use Lunar\Facades\DB;
use Lunar\Facades\ShippingManifest;
use Lunar\Jobs\Orders\MarkAsNewCustomer;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\CartLine;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Lunar\Validation\CartLine\CartLineAvailability;
use Lunar\Validation\CartLine\CartLineQuantity;

/**
 * Place a new order from an existing one.
 *
 * Preview prices the offered shipping methods and rolls the transaction back.
 * The save builds a private cart, runs the order-creation pipeline, and does
 * not write the source order. Stock is not moved, and placement events are not dispatched.
 */
final class DuplicateOrder extends AbstractAction
{
    /**
     * @var list<string>
     */
    private const LINE_META_OMITTED = [
        'preorder',
        'preorder_consultation_completed_at',
    ];

    /**
     * @var list<string>
     */
    private const ADDRESS_FIELDS = [
        'country_id',
        'first_name',
        'line_one',
        'city',
        'postcode',
    ];

    public function execute(
        OrderContract $order,
        ?string $paymentMethod,
        ?string $shippingIdentifier,
        ?int $shippingAmount,
    ): Order {
        /** @var Order $saved */
        $saved = DB::transaction(function () use ($order, $paymentMethod, $shippingIdentifier, $shippingAmount): Order {
            $locked = $this->lock($order);
            $this->guardAuthorized();
            $this->guardPayment($paymentMethod);
            $this->guardNegativeShipping($shippingAmount);

            $built = $this->buildCart($locked);
            $cart = $built['cart'];
            $this->guardLines($cart);

            $shippable = $cart->isShippable();
            $chosen = null;

            if ($shippable) {
                $chosen = $this->chosenMethod($locked, $cart, $shippingIdentifier);

                if ($shippingAmount === null) {
                    $this->fail('shipping');
                }
            } elseif (filled($shippingIdentifier)) {
                $this->fail('shipping');
            }

            $this->guardAddresses($cart, $shippable, $chosen);
            $this->applyCoupon($cart, $locked);

            $calculated = null;
            $override = false;

            if ($shippable && $chosen instanceof ShippingOption) {
                $this->selectShipping($cart, $chosen->getIdentifier());
                $calculated = $this->shippingTotalFor($locked, $cart, $chosen->getIdentifier());

                if ($calculated === null) {
                    $this->fail('shipping');
                }

                $override = $shippingAmount !== $calculated;
            } else {
                $this->clearShippingOption($cart);
            }

            $cart->meta = $this->cartMeta($locked, (string) $paymentMethod, $chosen, $override);
            $cart->save();

            if ($override && $chosen instanceof ShippingOption) {
                $this->applyShippingOverride($cart, $chosen, (int) $shippingAmount);
            }

            $cart->shippingOptionOverride = $override && $chosen instanceof ShippingOption
                ? $cart->shippingOptionOverride
                : null;
            $this->prepareDiscounts($locked);
            $cart->recalculate();

            $created = $this->runCreationPipeline($cart, CompletePreorderConsultation::statusFor((string) $paymentMethod));

            if ($override) {
                $this->storeTypedShipping($created, (int) $shippingAmount);
            }

            $this->place($created, $locked, (string) $paymentMethod);
            $this->copyLineNotes($created, $built['lineMap'], $locked);
            $this->copyTags($created, $locked);
            $this->takeStock($created);

            Cart::query()->whereKey($cart->id)->update([
                'order_id' => $created->id,
            ]);

            MarkAsNewCustomer::dispatch($created->id)->afterCommit();

            return $created->refresh();
        });

        return $saved;
    }

    /**
     * Payment options and priced shipping methods. The cart built here is rolled back.
     */
    public function preview(OrderContract $order): DuplicateOrderPreview
    {
        $level = DB::transactionLevel();
        DB::beginTransaction();

        try {
            $locked = $this->lock($order);
            $this->guardAuthorized();

            $paymentOptions = CompletePreorderConsultation::paymentOptions();
            $built = $this->buildCart($locked);
            $cart = $built['cart'];
            $methods = [];

            if ($this->linesCanBePriced($cart) && $cart->isShippable()) {
                $this->applyCoupon($cart, $locked);

                foreach ($this->offeredMethods($locked, $cart) as $option) {
                    $amount = $this->shippingTotalFor($locked, $cart, $option->getIdentifier());

                    if ($amount === null) {
                        continue;
                    }

                    $methods[] = new DuplicateOrderShippingMethod(
                        identifier: $option->getIdentifier(),
                        name: $option->getName(),
                        option: $option->getOption(),
                        amount: $amount,
                        collect: $option->collect,
                    );
                }
            }

            return new DuplicateOrderPreview(
                paymentOptions: $paymentOptions,
                paymentMethod: $this->defaultPayment($locked, $paymentOptions),
                shippingMethods: $methods,
                shippingMethod: $this->defaultShipping($locked, $methods),
            );
        } finally {
            DB::rollBack($level);
        }
    }

    private function lock(OrderContract $order): Order
    {
        /** @var Order $locked */
        $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
        $locked->load(['channel', 'currency', 'addresses', 'productLines.purchasable', 'tags']);

        return $locked;
    }

    private function guardAuthorized(): void
    {
        if (! UpdateOrderProductLines::userCanManageOrders()) {
            $this->fail('unauthorized');
        }
    }

    private function guardPayment(?string $paymentMethod): void
    {
        if (blank($paymentMethod) || ! in_array($paymentMethod, CompletePreorderConsultation::paymentOptions(), true)) {
            $this->fail('payment');
        }
    }

    private function guardNegativeShipping(?int $shippingAmount): void
    {
        if ($shippingAmount !== null && $shippingAmount < 0) {
            $this->fail('negative_shipping');
        }
    }

    /**
     * @return array{cart: Cart, lineMap: array<int, int>}
     */
    private function buildCart(Order $order): array
    {
        $cart = new Cart([
            'user_id' => $order->user_id,
            'customer_id' => $order->customer_id,
            'channel_id' => $order->channel_id,
            'currency_id' => $order->currency?->id,
        ]);
        $cart->save();

        foreach ($order->addresses as $address) {
            $cart->addresses()->create($this->addressAttributes($address));
        }

        $cart->unsetRelation('addresses');

        /** @var array<int, int> $lineMap */
        $lineMap = [];

        foreach ($order->productLines as $line) {
            $cartLine = $cart->lines()->create([
                'purchasable_id' => $line->purchasable_id,
                'purchasable_type' => $line->purchasable_type,
                'quantity' => $line->quantity,
                'meta' => $this->lineMeta($line->meta),
            ]);

            $lineMap[$cartLine->id] = (int) $line->id;
        }

        $cart->unsetRelation('lines');

        return [
            'cart' => $cart,
            'lineMap' => $lineMap,
        ];
    }

    private function guardLines(Cart $cart): void
    {
        $cart->load('lines.purchasable');

        if ($cart->lines->isEmpty()) {
            $this->fail('no_products');
        }

        foreach ($cart->lines as $line) {
            $purchasable = $line->purchasable;

            if (! $purchasable || ! $purchasable->isPurchasable()) {
                $this->fail('unavailable');
            }

            try {
                app(CartLineAvailability::class)->using(
                    cart: $cart,
                    cartLineId: $line->id,
                    purchasable: $purchasable,
                    quantity: $line->quantity,
                    meta: $this->metaArray($line->meta),
                )->validate();
            } catch (CartException) {
                $this->fail('unavailable');
            }

            try {
                app(CartLineQuantity::class)->using(
                    cart: $cart,
                    cartLineId: $line->id,
                    purchasable: $purchasable,
                    quantity: $line->quantity,
                    meta: $this->metaArray($line->meta),
                )->validate();
            } catch (CartException) {
                $this->fail('quantity');
            }

            if (! $purchasable->canBeFulfilledAtQuantity((int) $line->quantity)) {
                $this->fail('stock');
            }
        }
    }

    private function linesCanBePriced(Cart $cart): bool
    {
        $cart->load('lines.purchasable');

        return $cart->lines->isNotEmpty()
            && $cart->lines->every(fn (CartLine $line): bool => $line->purchasable !== null);
    }

    private function chosenMethod(Order $order, Cart $cart, ?string $identifier): ShippingOption
    {
        if (blank($identifier) || $this->isLockerIdentifier((string) $identifier)) {
            $this->fail('shipping');
        }

        $chosen = $this->offeredMethods($order, $cart)
            ->first(fn (ShippingOption $option): bool => $option->getIdentifier() === $identifier);

        if (! $chosen) {
            $this->fail('shipping');
        }

        return $chosen;
    }

    /**
     * @return Collection<int, ShippingOption>
     */
    private function offeredMethods(Order $order, Cart $cart): Collection
    {
        if (! $cart->isShippable()) {
            return collect();
        }

        $this->prepareDiscounts($order);
        ShippingManifest::clearOptions();
        $cart->unsetRelation('shippingAddress');
        $cart->unsetRelation('lines');

        return ShippingManifest::getOptions($cart)
            ->reject(fn (ShippingOption $option): bool => $this->isLocker($option))
            ->values();
    }

    private function guardAddresses(Cart $cart, bool $shippable, ?ShippingOption $chosen): void
    {
        $cart->unsetRelation('billingAddress');
        $cart->unsetRelation('shippingAddress');

        if (! $this->addressIsComplete($cart->billingAddress)) {
            $this->fail('address');
        }

        if ($shippable && $chosen && ! $chosen->collect && ! $this->addressIsComplete($cart->shippingAddress)) {
            $this->fail('address');
        }
    }

    private function addressIsComplete(mixed $address): bool
    {
        if (! $address) {
            return false;
        }

        foreach (self::ADDRESS_FIELDS as $field) {
            if (blank($address->{$field})) {
                return false;
            }
        }

        return true;
    }

    private function applyCoupon(Cart $cart, Order $order): void
    {
        $coupon = $this->couponFromOrder($order);
        $cart->coupon_code = $coupon;
        $cart->save();
    }

    /**
     * Shipping total for one method with no price override, or null when it is not offered.
     */
    private function shippingTotalFor(Order $order, Cart $cart, string $identifier): ?int
    {
        $this->selectShipping($cart, $identifier);
        $cart->shippingOptionOverride = null;
        ShippingManifest::clearOptions();
        $this->prepareDiscounts($order);
        $cart->unsetRelation('lines');
        $cart->recalculate();

        $option = $cart->getShippingOption();

        if (! $option || $option->getIdentifier() !== $identifier) {
            return null;
        }

        return (int) ($cart->shippingTotal?->value ?? 0);
    }

    private function selectShipping(Cart $cart, string $identifier): void
    {
        $address = $cart->shippingAddress()->first();

        if (! $address) {
            $billing = $cart->billingAddress()->first();
            $attributes = $billing ? $this->addressAttributes($billing) : [];
            $attributes['type'] = 'shipping';
            $attributes['shipping_option'] = $identifier;
            $cart->addresses()->create($attributes);
        } else {
            $address->update([
                'shipping_option' => $identifier,
            ]);
        }

        $cart->unsetRelation('shippingAddress');
        $cart->unsetRelation('billingAddress');
        $cart->unsetRelation('addresses');
    }

    private function clearShippingOption(Cart $cart): void
    {
        $address = $cart->shippingAddress()->first();

        if ($address && filled($address->shipping_option)) {
            $address->update([
                'shipping_option' => null,
            ]);
        }

        $cart->unsetRelation('shippingAddress');
        $cart->unsetRelation('addresses');
        $cart->shippingOptionOverride = null;
    }

    private function applyShippingOverride(Cart $cart, ShippingOption $option, int $amount): void
    {
        $cart->loadMissing('currency');
        $meta = $option->meta ?? [];
        $meta['shipping_price_override'] = true;

        $cart->shippingOptionOverride = new ShippingOption(
            name: $option->getName(),
            description: $option->getDescription(),
            identifier: $option->getIdentifier(),
            price: new Price($amount, $cart->currency, 1),
            taxClass: $option->getTaxClass(),
            option: $option->getOption(),
            collect: $option->collect,
            meta: $meta,
        );
    }

    /**
     * The creation pipeline reloads the cart before the shipping line, which drops
     * an in-memory override. Write the confirmed price onto that line afterwards.
     */
    private function storeTypedShipping(Order $order, int $amount): void
    {
        $line = $order->shippingLines()->first();

        if (! $line) {
            return;
        }

        $meta = $this->metaArray($line->meta);
        $meta['shipping_price_override'] = true;

        $line->fill([
            'unit_price' => $amount,
            'sub_total' => $amount,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => $amount,
            'meta' => $meta,
        ])->saveQuietly();

        $current = (int) ($order->shipping_total->value ?? 0);
        $orderMeta = $this->metaArray($order->meta);
        $orderMeta['shipping_price_override'] = true;
        $order->meta = $orderMeta;

        if ($current !== $amount) {
            $order->total = (int) ($order->total->value ?? 0) - $current + $amount;
            $order->shipping_total = $amount;
        }

        $order->saveQuietly();
    }

    /**
     * The order is born in its payment status.
     *
     * A split does the same. The storefront emails a card payment link, or bank-transfer
     * instructions, when it sees that first save. A later status change would also move stock.
     */
    private function runCreationPipeline(Cart $cart, string $status): Order
    {
        $draftStatus = config('lunar.orders.draft_status');
        config(['lunar.orders.draft_status' => $status]);

        try {
            $order = new Order;
            $order->cart_id = $cart->id;
            $order->fingerprint = $cart->fingerprint();
            $order->setRelation('cart', $cart);

            /** @var Order $created */
            $created = app(Pipeline::class)
                ->send($order)
                ->through(config('lunar.orders.pipelines.creation', []))
                ->thenReturn();

            return $created;
        } finally {
            config(['lunar.orders.draft_status' => $draftStatus]);
        }
    }

    /**
     * Reserve the copied quantities.
     *
     * A preorder variant keeps its stock. The meta flag tells the storefront this order
     * already took stock, so a later shipment status does not take it again.
     */
    private function takeStock(Order $order): void
    {
        $order->load('productLines.purchasable');

        foreach ($order->productLines as $line) {
            $purchasable = $line->purchasable;

            if (! $purchasable) {
                continue;
            }

            $variant = ProductVariant::query()->whereKey($purchasable->getKey())->lockForUpdate()->first();

            if (! $variant) {
                continue;
            }

            $variant->decreaseStock((int) $line->quantity)->save();
        }

        $meta = $this->metaArray($order->meta);
        $meta['stock_taken'] = true;
        $order->meta = $meta;
        $order->saveQuietly();
    }

    private function place(Order $order, Order $source, string $paymentMethod): void
    {
        $order->placed_at = now();
        $order->notes = $source->notes;
        $order->customer_reference = $source->customer_reference;
        $order->status = CompletePreorderConsultation::statusFor($paymentMethod);
        $order->saveQuietly();
    }

    /**
     * @param  array<int, int>  $lineMap
     */
    private function copyLineNotes(Order $order, array $lineMap, Order $source): void
    {
        $sourceLines = $source->productLines->keyBy('id');
        $order->unsetRelation('lines');
        $order->load('lines');

        $cartLines = CartLine::query()->whereIn('id', array_keys($lineMap))->get();
        $used = [];

        foreach ($cartLines as $cartLine) {
            $sourceLine = $sourceLines->get($lineMap[$cartLine->id] ?? 0);

            if (! $sourceLine) {
                continue;
            }

            $orderLine = $order->lines->first(function (OrderLine $line) use ($cartLine, $used): bool {
                if (isset($used[$line->id])) {
                    return false;
                }

                return $line->purchasable_type === $cartLine->purchasable_type
                    && (int) $line->purchasable_id === (int) $cartLine->purchasable_id
                    && $this->metaArray($line->meta) == $this->metaArray($cartLine->meta);
            });

            if (! $orderLine) {
                $orderLine = $order->lines->first(function (OrderLine $line) use ($cartLine, $used): bool {
                    if (isset($used[$line->id])) {
                        return false;
                    }

                    return $line->purchasable_type === $cartLine->purchasable_type
                        && (int) $line->purchasable_id === (int) $cartLine->purchasable_id;
                });
            }

            if (! $orderLine) {
                continue;
            }

            $used[$orderLine->id] = true;
            $orderLine->notes = $sourceLine->notes;
            $orderLine->saveQuietly();
        }
    }

    private function copyTags(Order $order, Order $source): void
    {
        $tagIds = $source->tags->pluck('id')->all();

        if ($tagIds === []) {
            return;
        }

        $order->tags()->sync($tagIds);
    }

    /**
     * @return array<string, mixed>
     */
    private function cartMeta(Order $order, string $paymentMethod, ?ShippingOption $chosen, bool $override): array
    {
        $source = $this->metaArray($order->meta);
        $meta = [
            'payment_option' => $paymentMethod,
            'duplicated_from_order_id' => $order->id,
        ];

        foreach (['language_locale', 'is_guest', 'isBillingSameAsShipping'] as $key) {
            if (array_key_exists($key, $source)) {
                $meta[$key] = $source[$key];
            }
        }

        $sourcePayment = (string) ($source['payment_option'] ?? '');

        if ($paymentMethod !== '' && $paymentMethod === $sourcePayment && array_key_exists('payment_type', $source)) {
            $meta['payment_type'] = $source['payment_type'];
        }

        if ($chosen instanceof ShippingOption) {
            $meta['shippingType'] = $chosen->collect ? 'pickup' : 'courier';
        }

        if ($override) {
            $meta['shipping_price_override'] = true;
        }

        return $meta;
    }

    /**
     * @param  list<string>  $paymentOptions
     */
    private function defaultPayment(Order $order, array $paymentOptions): ?string
    {
        $source = (string) ($this->metaArray($order->meta)['payment_option'] ?? '');

        if ($source !== '' && in_array($source, $paymentOptions, true)) {
            return $source;
        }

        if (in_array('cash-on-delivery', $paymentOptions, true)) {
            return 'cash-on-delivery';
        }

        return $paymentOptions[0] ?? null;
    }

    /**
     * @param  list<DuplicateOrderShippingMethod>  $methods
     */
    private function defaultShipping(Order $order, array $methods): ?string
    {
        if ($methods === []) {
            return null;
        }

        $source = $this->sourceShippingIdentifier($order);

        foreach ($methods as $method) {
            if ($source !== null && $method->identifier === $source) {
                return $method->identifier;
            }
        }

        return $methods[0]->identifier;
    }

    private function sourceShippingIdentifier(Order $order): ?string
    {
        $identifier = $order->shippingLines()->value('identifier');

        return filled($identifier) ? (string) $identifier : null;
    }

    private function prepareDiscounts(Order $order): void
    {
        $manager = app(DiscountManagerInterface::class)->resetDiscounts();

        if ($order->channel) {
            $manager->channel($order->channel);
        }
    }

    private function couponFromOrder(Order $order): ?string
    {
        $discountIds = collect($order->discount_breakdown ?? [])
            ->pluck('discount_id')
            ->filter()
            ->unique()
            ->values();

        if ($discountIds->isEmpty()) {
            return null;
        }

        $coupon = Discount::query()
            ->whereIn('id', $discountIds)
            ->whereNotNull('coupon')
            ->value('coupon');

        return filled($coupon) ? (string) $coupon : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function lineMeta(mixed $meta): array
    {
        $values = $this->metaArray($meta);

        foreach (self::LINE_META_OMITTED as $key) {
            unset($values[$key]);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function addressAttributes(CartAddress|OrderAddress $address): array
    {
        $attributes = [];

        foreach ((new CartAddress)->getFillable() as $field) {
            if ($field === 'meta') {
                $attributes['meta'] = $this->metaArray($address->meta);

                continue;
            }

            $attributes[$field] = $address->{$field} ?? null;
        }

        return $attributes;
    }

    private function isLocker(ShippingOption $option): bool
    {
        return $this->isLockerIdentifier($option->getIdentifier());
    }

    private function isLockerIdentifier(string $identifier): bool
    {
        return str_ends_with(strtolower($identifier), '-locker');
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

    /**
     * @return never
     */
    private function fail(string $reason): void
    {
        throw new DuplicateOrderException($reason);
    }
}
