<?php

namespace Lunar\Actions\Orders;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Lunar\Actions\AbstractAction;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\DataTypes\ShippingOption;
use Lunar\Events\OrderProductLinesRecalculated;
use Lunar\Exceptions\InsufficientStockException;
use Lunar\Exceptions\NonPurchasableItemException;
use Lunar\Exceptions\Orders\LastOrderProductLineException;
use Lunar\Exceptions\Orders\LockedOrderProductLineEditException;
use Lunar\Exceptions\Orders\MissingOrderCartException;
use Lunar\Exceptions\Orders\OrderProductLineNotFoundException;
use Lunar\Exceptions\Orders\OrderProductLinePurchasableMissingException;
use Lunar\Exceptions\Orders\UnauthorizedOrderProductLineEditException;
use Lunar\Exceptions\Orders\UnpricedOrderShippingException;
use Lunar\Exceptions\OutOfStockException;
use Lunar\Facades\DB;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Lunar\Utils\Arr;
use Lunar\Validation\CartLine\CartLineAvailability;
use Lunar\Validation\CartLine\CartLineQuantity;

/**
 * Apply one product-line change by recalculating the linked cart.
 *
 * The order-creation pipeline is not used. It would reset status, reference, and meta, and it would spend loyalty points again.
 */
final class UpdateOrderProductLines extends AbstractAction
{
    /**
     * @var list<string>
     */
    private const LOYALTY_META_KEYS = [
        'loyalty_points_to_redeem',
        'loyalty_discount_minor',
    ];

    public function execute(OrderContract $order, OrderProductLineChange $change): Order
    {
        /** @var Order $saved */
        $saved = DB::transaction(function () use ($order, $change): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if (! self::userCanManageOrders()) {
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

            $previousPoints = (int) ($this->metaValue($locked, 'loyalty_points_to_redeem') ?? 0);
            $before = $this->quantitiesByPurchasable($locked->productLines()->with('purchasable')->get());

            $this->alignCart($locked, $cart);
            $this->rebuildCartLines($locked, $cart);
            $this->applyChange($locked, $cart, $change);

            $after = $this->quantitiesByPurchasable($cart->lines()->with('purchasable')->get());
            $this->syncStock($locked, $before, $after);

            $cart->unsetRelation('lines');
            $cart->recalculate();

            $this->assertShippingPriced($locked, $cart);
            $this->copyCalculatedOrder($locked, $cart);

            event(new OrderProductLinesRecalculated($locked, $previousPoints));

            return $locked->refresh();
        });

        return $saved;
    }

    /**
     * Whether the authenticated staff member may manage orders.
     *
     * An admin (the staff flag or the admin role) or the `sales:manage-orders` permission is enough.
     */
    public static function userCanManageOrders(): bool
    {
        $user = self::authenticatedUser();

        if ($user === null) {
            return false;
        }

        if (method_exists($user, 'getAttributes')) {
            $attributes = $user->getAttributes();

            if (array_key_exists('admin', $attributes) && $attributes['admin']) {
                return true;
            }
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        return $user->can('sales:manage-orders');
    }

    private static function authenticatedUser(): ?Authenticatable
    {
        $user = auth()->user();

        if ($user !== null) {
            return $user;
        }

        if (! config('auth.guards.staff')) {
            return null;
        }

        return auth('staff')->user();
    }

    private function alignCart(Order $order, Cart $cart): void
    {
        $cart->customer_id = $order->customer_id;
        $cart->channel_id = $order->channel_id;
        $cart->currency_id = $order->currency?->id ?? $cart->currency_id;

        if (blank($cart->coupon_code)) {
            $coupon = $this->couponFromOrder($order);

            if (filled($coupon)) {
                $cart->coupon_code = $coupon;
            }
        }

        $cartMeta = $this->metaArray($cart->meta);

        foreach ($this->loyaltyMeta($order->meta) as $key => $value) {
            $cartMeta[$key] = $value;
        }

        $cart->meta = $cartMeta;
        $cart->save();

        $identifier = $order->shippingLines()->value('identifier');

        if (! filled($identifier)) {
            return;
        }

        $address = $cart->shippingAddress()->first();

        if ($address && blank($address->shipping_option)) {
            $address->update([
                'shipping_option' => $identifier,
            ]);
        }
    }

    private function rebuildCartLines(Order $order, Cart $cart): void
    {
        $cart->lines()->delete();

        foreach ($order->productLines()->get() as $line) {
            $cart->lines()->create([
                'purchasable_id' => $line->purchasable_id,
                'purchasable_type' => $line->purchasable_type,
                'quantity' => $line->quantity,
                'meta' => $this->metaArray($line->meta),
            ]);
        }

        $cart->unsetRelation('lines');
    }

    private function applyChange(Order $order, Cart $cart, OrderProductLineChange $change): void
    {
        if ($change->type === 'set_quantity') {
            $this->setQuantity($order, $cart, $change);

            return;
        }

        if ($change->type === 'add') {
            $this->addVariant($cart, $change);

            return;
        }

        $this->removeLine($order, $cart, $change);
    }

    private function setQuantity(Order $order, Cart $cart, OrderProductLineChange $change): void
    {
        $orderLine = $this->productLine($order, $change->orderLineId);
        $purchasable = $orderLine->purchasable;

        if (! $purchasable) {
            throw new OrderProductLinePurchasableMissingException;
        }

        $meta = $this->metaArray($orderLine->meta);
        $cartLine = $this->findCartLine($cart, $orderLine->purchasable_type, $orderLine->purchasable_id, $meta);

        if (! $cartLine) {
            throw new OrderProductLineNotFoundException;
        }

        $this->assertQuantity($cart, $purchasable, (int) $change->quantity, $meta, $cartLine->id);

        $cartLine->update([
            'quantity' => $change->quantity,
        ]);
    }

    private function addVariant(Cart $cart, OrderProductLineChange $change): void
    {
        $variant = ProductVariant::query()->find($change->purchasableId);

        if (! $variant || $variant->getMorphClass() !== $change->purchasableType) {
            throw new OrderProductLinePurchasableMissingException;
        }

        if (! $variant->isPurchasable()) {
            throw new NonPurchasableItemException($variant::class);
        }

        app(CartLineAvailability::class)->using(
            cart: $cart,
            purchasable: $variant,
            quantity: $change->quantity,
        )->validate();

        $meta = [];
        $existing = $this->findCartLine($cart, $variant->getMorphClass(), $variant->id, $meta);

        if ($existing) {
            $resulting = $existing->quantity + (int) $change->quantity;
            $this->assertQuantity($cart, $variant, $resulting, $meta, $existing->id);
            $existing->update([
                'quantity' => $resulting,
            ]);

            return;
        }

        $this->assertQuantity($cart, $variant, (int) $change->quantity, $meta);

        $cart->lines()->create([
            'purchasable_id' => $variant->id,
            'purchasable_type' => $variant->getMorphClass(),
            'quantity' => $change->quantity,
            'meta' => $meta,
        ]);
    }

    private function removeLine(Order $order, Cart $cart, OrderProductLineChange $change): void
    {
        $orderLine = $this->productLine($order, $change->orderLineId);

        if ($order->productLines()->count() <= 1) {
            throw new LastOrderProductLineException;
        }

        $cartLine = $this->findCartLine(
            $cart,
            $orderLine->purchasable_type,
            $orderLine->purchasable_id,
            $this->metaArray($orderLine->meta),
        );

        $cartLine?->delete();
    }

    private function productLine(Order $order, ?int $orderLineId): OrderLine
    {
        $orderLine = $order->productLines()->whereKey($orderLineId)->first();

        if (! $orderLine) {
            throw new OrderProductLineNotFoundException;
        }

        return $orderLine;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function assertQuantity(Cart $cart, ProductVariant $variant, int $quantity, array $meta, ?int $cartLineId = null): void
    {
        app(CartLineQuantity::class)->using(
            cart: $cart,
            purchasable: $variant,
            quantity: $quantity,
            meta: $meta,
            cartLineId: $cartLineId,
        )->validate();
    }

    /**
     * @param  array<string, array{quantity: int, purchasable: mixed}>  $before
     * @param  array<string, array{quantity: int, purchasable: mixed}>  $after
     */
    private function syncStock(Order $order, array $before, array $after): void
    {
        if (! $this->stockIsTaken($order)) {
            return;
        }

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $old = $before[$key]['quantity'] ?? 0;
            $new = $after[$key]['quantity'] ?? 0;
            $delta = $new - $old;

            if ($delta === 0) {
                continue;
            }

            $purchasable = $after[$key]['purchasable'] ?? $before[$key]['purchasable'] ?? null;

            if (! $purchasable) {
                continue;
            }

            $variant = $purchasable->fresh();

            if (! $variant) {
                continue;
            }

            if ($delta > 0) {
                $this->assertAddedQuantityIsInStock($variant, $delta);
                $variant->decreaseStock($delta)->save();

                continue;
            }

            $variant->increaseStock(abs($delta))->save();
        }
    }

    /**
     * Reject the added units when the variant cannot fulfill them.
     */
    private function assertAddedQuantityIsInStock(ProductVariant $variant, int $quantity): void
    {
        if (! $variant->canBeFulfilledAtQuantity(1)) {
            throw new OutOfStockException;
        }

        if (! $variant->canBeFulfilledAtQuantity($quantity)) {
            throw new InsufficientStockException;
        }
    }

    private function stockIsTaken(Order $order): bool
    {
        $decrease = array_values((array) config('lunar-frontend.orders.stock.decrease', []));
        $increase = array_values((array) config('lunar-frontend.orders.stock.increase', []));
        $statuses = array_values(array_unique([...$decrease, ...$increase]));

        if ($statuses === []) {
            return false;
        }

        $latest = $order->getActivitiesByStatuses($statuses)->first();

        if ($latest === null) {
            return false;
        }

        $newStatus = $latest->properties['new'] ?? null;

        return in_array($newStatus, $decrease, true);
    }

    /**
     * @param  iterable<int, CartLine|OrderLine>  $lines
     * @return array<string, array{quantity: int, purchasable: mixed}>
     */
    private function quantitiesByPurchasable(iterable $lines): array
    {
        $totals = [];

        foreach ($lines as $line) {
            $key = $line->purchasable_type.':'.$line->purchasable_id;
            $totals[$key]['quantity'] = ($totals[$key]['quantity'] ?? 0) + (int) $line->quantity;
            $totals[$key]['purchasable'] = $line->purchasable;
        }

        return $totals;
    }

    private function assertShippingPriced(Order $order, Cart $cart): void
    {
        $identifier = $order->shippingLines()->value('identifier');

        if (! filled($identifier)) {
            return;
        }

        $option = $cart->getShippingOption();

        if (! $option || $option->getIdentifier() !== $identifier) {
            throw new UnpricedOrderShippingException;
        }
    }

    private function copyCalculatedOrder(Order $order, Cart $cart): void
    {
        $this->writeProductLines($order, $cart);
        $this->writeShippingLine($order, $cart);

        $order->unsetRelation('lines');
        $order->load('lines');

        $meta = $this->metaArray($order->meta);
        $cartLoyalty = $this->loyaltyMeta($cart->meta);

        foreach (self::LOYALTY_META_KEYS as $key) {
            if (array_key_exists($key, $cartLoyalty)) {
                $meta[$key] = $cartLoyalty[$key];

                continue;
            }

            unset($meta[$key]);
        }

        $order->update([
            'sub_total' => $cart->subTotal?->value ?? 0,
            'discount_total' => $cart->discountTotal?->value ?? 0,
            'discount_breakdown' => $this->discountBreakdownFromCart($order, $cart),
            'shipping_total' => $cart->shippingTotal?->value ?? 0,
            'shipping_breakdown' => $cart->shippingBreakdown ?? new ShippingBreakdown,
            'tax_breakdown' => $cart->taxBreakdown ?? new TaxBreakdown,
            'tax_total' => $cart->taxTotal?->value ?? 0,
            'total' => $cart->total?->value ?? 0,
            'fingerprint' => $cart->fingerprint(),
            'meta' => $meta,
        ]);
    }

    private function writeProductLines(Order $order, Cart $cart): void
    {
        $order->load('lines');
        $keptIds = [];

        foreach ($cart->lines as $cartLine) {
            $orderLine = $order->lines->first(function (OrderLine $line) use ($cartLine): bool {
                if ($line->type === 'shipping') {
                    return false;
                }

                return $line->purchasable_type === $cartLine->purchasable_type
                    && (int) $line->purchasable_id === (int) $cartLine->purchasable_id
                    && $this->metaMatches($line->meta, $cartLine->meta);
            }) ?? new OrderLine;

            $purchasable = $cartLine->purchasable;

            $orderLine->fill([
                'order_id' => $order->id,
                'purchasable_type' => $cartLine->purchasable_type,
                'purchasable_id' => $cartLine->purchasable_id,
                'type' => $purchasable?->getType() ?? $orderLine->type ?? 'physical',
                'description' => $purchasable?->getDescription() ?? $orderLine->description,
                'option' => $purchasable?->getOption(),
                'identifier' => $purchasable?->getIdentifier() ?? $orderLine->identifier,
                'unit_price' => $cartLine->unitPrice?->value ?? 0,
                'unit_quantity' => $purchasable?->getUnitQuantity() ?? $orderLine->unit_quantity ?? 1,
                'quantity' => $cartLine->quantity,
                'sub_total' => $cartLine->subTotal?->value ?? 0,
                'discount_total' => $cartLine->discountTotal?->value ?? 0,
                'tax_breakdown' => $cartLine->taxBreakdown ?? new TaxBreakdown,
                'tax_total' => $cartLine->taxAmount?->value ?? 0,
                'total' => $cartLine->total?->value ?? 0,
                'notes' => $orderLine->exists ? $orderLine->notes : null,
                'meta' => $this->metaArray($cartLine->meta),
            ])->save();

            $keptIds[] = $orderLine->id;
        }

        if ($keptIds !== []) {
            $order->productLines()->whereNotIn('id', $keptIds)->delete();
        }
    }

    private function writeShippingLine(Order $order, Cart $cart): void
    {
        $identifier = $order->shippingLines()->value('identifier');

        if (! filled($identifier)) {
            return;
        }

        $option = $cart->getShippingOption();

        if (! $option) {
            return;
        }

        $order->load('lines');

        $shippingLine = $order->lines->first(function (OrderLine $line) use ($option): bool {
            return $line->type === 'shipping' && $line->identifier === $option->getIdentifier();
        }) ?? new OrderLine;

        $address = $cart->shippingAddress;

        $shippingLine->fill([
            'order_id' => $order->id,
            'purchasable_type' => null,
            'purchasable_id' => null,
            'type' => 'shipping',
            'description' => $option->getName(),
            'option' => $option->getOption(),
            'identifier' => $option->getIdentifier(),
            'unit_price' => $option->price->value,
            'unit_quantity' => $option->getUnitQuantity(),
            'quantity' => 1,
            'sub_total' => $address?->shippingSubTotal?->value ?? $option->price->value,
            'discount_total' => $address?->shippingSubTotal?->discountTotal?->value ?: 0,
            'tax_breakdown' => $address?->taxBreakdown ?? new TaxBreakdown,
            'tax_total' => $address?->shippingTaxTotal?->value ?? 0,
            'total' => $address?->shippingTotal?->value ?? $option->price->value,
            'notes' => null,
            'meta' => $option->meta,
        ])->save();
    }

    /**
     * @return Collection<int, object>
     */
    private function discountBreakdownFromCart(Order $order, Cart $cart): Collection
    {
        $mappedLines = [];

        foreach ($order->lines as $orderLine) {
            if ($orderLine->type === 'shipping') {
                continue;
            }

            $cartLine = $cart->lines->first(function (CartLine $line) use ($orderLine): bool {
                return $line->purchasable_type === $orderLine->purchasable_type
                    && (int) $line->purchasable_id === (int) $orderLine->purchasable_id
                    && $this->metaMatches($line->meta, $orderLine->meta);
            });

            if ($cartLine) {
                $mappedLines[$cartLine->id] = $orderLine;
            }
        }

        return collect($cart->discountBreakdown ?? [])->map(function ($discount) use ($mappedLines) {
            return (object) [
                'discount_id' => $discount->discount->id,
                'lines' => collect($discount->lines)->map(function ($discountLine) use ($mappedLines) {
                    $orderLine = $mappedLines[$discountLine->line->id] ?? null;

                    if (! $orderLine) {
                        return null;
                    }

                    return (object) [
                        'quantity' => $discountLine->quantity,
                        'line' => $orderLine,
                        'amount' => $discountLine->amount,
                    ];
                })->filter()->values(),
                'total' => $discount->price,
            ];
        })->values();
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

    private function findCartLine(Cart $cart, ?string $type, ?int $id, array $meta): ?CartLine
    {
        if ($type === null || $id === null) {
            return null;
        }

        return $cart->lines()
            ->where('purchasable_type', $type)
            ->where('purchasable_id', $id)
            ->get()
            ->first(fn (CartLine $line): bool => $this->metaMatches($line->meta, $meta));
    }

    private function metaMatches(mixed $left, mixed $right): bool
    {
        $diff = Arr::diff($this->metaArray($left), $this->metaArray($right));

        return empty($diff->new) && empty($diff->edited) && empty($diff->removed);
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
     * @return array<string, mixed>
     */
    private function loyaltyMeta(mixed $meta): array
    {
        $values = $this->metaArray($meta);
        $loyalty = [];

        foreach (self::LOYALTY_META_KEYS as $key) {
            if (array_key_exists($key, $values)) {
                $loyalty[$key] = $values[$key];
            }
        }

        return $loyalty;
    }

    private function metaValue(Order $order, string $key): mixed
    {
        if ($order->meta === null || ! isset($order->meta[$key])) {
            return null;
        }

        return $order->meta[$key];
    }
}
