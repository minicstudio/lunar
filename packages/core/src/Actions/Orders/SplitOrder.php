<?php

namespace Lunar\Actions\Orders;

use Illuminate\Support\Collection;
use Lunar\Actions\AbstractAction;
use Lunar\Base\DiscountManagerInterface;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Events\OrderSplitLoyaltyMoving;
use Lunar\Events\OrdersSplit;
use Lunar\Exceptions\Orders\InvalidOrderSplitException;
use Lunar\Exceptions\Orders\LockedOrderSplitException;
use Lunar\Exceptions\Orders\UnauthorizedOrderSplitException;
use Lunar\Facades\DB;
use Lunar\Facades\Pricing;
use Lunar\Facades\ShippingManifest;
use Lunar\Facades\Taxes;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\CartLine;
use Lunar\Models\Contracts\Order as OrderContract;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRateAmount;
use Throwable;

/**
 * Preview and confirm a split of one placed order into two or more orders.
 *
 * Preview and confirm use the same cart calculation. Preview rolls the transaction back.
 * Confirm stores every new order, or none. The order-creation pipeline is not used.
 */
final class SplitOrder extends AbstractAction
{
    /**
     * @var list<string>
     */
    private const LOYALTY_META_KEYS = [
        'loyalty_points_to_redeem',
        'loyalty_discount_minor',
    ];

    /**
     * @var list<string>
     */
    private const COPIED_META_KEYS = [
        'language_locale',
        'shippingType',
        'is_guest',
        'isBillingSameAsShipping',
    ];

    /**
     * @var list<string>
     */
    private const OFFLINE_PAYMENT_OPTIONS = [
        'cash-on-delivery',
        'cash-in-hand',
        'offline',
    ];

    public function execute(OrderContract $order, SplitOrderRequest $request): Order
    {
        /** @var Order $saved */
        $saved = DB::transaction(function () use ($order, $request): Order {
            $locked = $this->lock($order);
            $this->guard($locked);

            $errors = $this->assignmentErrors($locked, $request);

            if ($errors !== []) {
                throw new InvalidOrderSplitException($errors);
            }

            $calculated = $this->calculate($locked, $request);
            $created = $this->persist($locked, $request, $calculated);

            $points = (int) ($this->metaValue($locked, 'loyalty_points_to_redeem') ?? 0);
            $applied = $request->applyLoyalty && $points > 0;

            event(new OrderSplitLoyaltyMoving(
                $locked,
                $applied ? $created->get((string) $request->loyaltyTarget) : null,
                $applied,
            ));

            $this->markOriginalSplit($locked, $created);

            $orders = $created->values()->all();

            DB::afterCommit(function () use ($orders): void {
                event(new OrdersSplit($orders));
            });

            return $locked->refresh();
        });

        return $saved;
    }

    /**
     * Calculate the targets and roll the transaction back, including any cart rows.
     */
    public function preview(OrderContract $order, SplitOrderRequest $request): SplitOrderPreview
    {
        DB::beginTransaction();

        try {
            $locked = $this->lock($order);
            $this->guard($locked);
            $errors = $this->assignmentErrors($locked, $request);
            $calculated = $this->calculate($locked, $request);

            return new SplitOrderPreview(
                targets: array_map(fn (array $target): SplitOrderPreviewTarget => $this->previewTarget($target), $calculated),
                errors: $errors,
            );
        } finally {
            DB::rollBack();
        }
    }

    private function lock(OrderContract $order): Order
    {
        /** @var Order $locked */
        $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
        $locked->load(['channel', 'currency', 'addresses', 'productLines']);

        return $locked;
    }

    private function guard(Order $order): void
    {
        if (! UpdateOrderProductLines::userCanManageOrders()) {
            throw new UnauthorizedOrderSplitException;
        }

        $gates = GetOrderSplitGates::run($order);

        if (! $gates->passes()) {
            throw new LockedOrderSplitException($gates->failed);
        }
    }

    /**
     * @return list<string>
     */
    private function assignmentErrors(Order $order, SplitOrderRequest $request): array
    {
        $errors = [];
        $lines = $order->productLines->keyBy('id');
        $assigned = [];
        $seenKeys = [];

        if ($request->targets === []) {
            $errors[] = 'too_few_targets';
        }

        foreach ($request->targets as $target) {
            if ($target->key === '' || isset($seenKeys[$target->key])) {
                $errors[] = 'lines_mismatch';
            }

            $seenKeys[$target->key] = true;

            if ($target->shippingMinor !== null && $target->shippingMinor < 0) {
                $errors[] = 'negative_shipping';
            }

            if (blank($target->paymentOption)) {
                $errors[] = 'lines_mismatch';
            }

            foreach ($target->quantities as $lineId => $quantity) {
                $lineId = (int) $lineId;

                if ($quantity < 0 || ! $lines->has($lineId)) {
                    $errors[] = 'lines_mismatch';

                    continue;
                }

                $assigned[$lineId] = ($assigned[$lineId] ?? 0) + $quantity;
            }
        }

        foreach ($lines as $line) {
            $sum = $assigned[$line->id] ?? 0;

            if ($sum < (int) $line->quantity) {
                $errors[] = 'unassigned';
            } elseif ($sum > (int) $line->quantity) {
                $errors[] = 'assigned_twice';
            }
        }

        $nonEmpty = 0;

        foreach ($request->targets as $target) {
            if (array_sum($target->quantities) > 0) {
                $nonEmpty++;
            }
        }

        if ($nonEmpty < 2) {
            $errors[] = 'too_few_targets';
        }

        if (filled($this->couponFromOrder($order)) && $request->applyCoupon && ! $this->targetIsNonEmpty($request, $request->couponTarget)) {
            $errors[] = 'coupon';
        }

        $points = (int) ($this->metaValue($order, 'loyalty_points_to_redeem') ?? 0);

        if ($points > 0 && $request->applyLoyalty && ! $this->targetIsNonEmpty($request, $request->loyaltyTarget)) {
            $errors[] = 'loyalty';
        }

        return array_values(array_unique($errors));
    }

    private function targetIsNonEmpty(SplitOrderRequest $request, ?string $key): bool
    {
        if ($key === null || $key === '') {
            return false;
        }

        foreach ($request->targets as $target) {
            if ($target->key === $key && array_sum($target->quantities) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{
     *     key: string,
     *     cart: Cart,
     *     lineMap: array<int, int>,
     *     shippingMinor: int,
     *     shippingPricingUnavailable: bool,
     *     shippingIdentifier: ?string,
     *     paymentOption: string,
     *     paymentType: ?string,
     *     option: ?ShippingOption
     * }>
     */
    private function calculate(Order $order, SplitOrderRequest $request): array
    {
        $coupon = $this->couponFromOrder($order);
        $calculated = [];

        foreach ($request->targets as $target) {
            $quantities = array_filter(
                $target->quantities,
                fn (int $quantity): bool => $quantity > 0,
            );

            if ($quantities === []) {
                continue;
            }

            $calculated[] = $this->calculateTarget($order, $request, $target, $quantities, $coupon);
        }

        return $calculated;
    }

    /**
     * @param  array<int, int>  $quantities
     * @return array{
     *     key: string,
     *     cart: Cart,
     *     lineMap: array<int, int>,
     *     shippingMinor: int,
     *     shippingPricingUnavailable: bool,
     *     shippingIdentifier: ?string,
     *     paymentOption: string,
     *     paymentType: ?string,
     *     option: ?ShippingOption
     * }
     */
    private function calculateTarget(Order $order, SplitOrderRequest $request, SplitOrderTarget $target, array $quantities, ?string $coupon): array
    {
        $currency = $order->currency;
        $shippingLine = $order->shippingLines()->first();
        $identifier = filled($shippingLine?->identifier) ? (string) $shippingLine->identifier : null;

        $cart = new Cart([
            'currency_id' => $currency?->id,
            'channel_id' => $order->channel_id,
            'customer_id' => $order->customer_id,
            'user_id' => $order->user_id,
        ]);
        $cart->save();

        foreach ($order->addresses as $address) {
            $attributes = $this->addressAttributes($address);

            if ($address->type === 'shipping' && $identifier !== null) {
                $attributes['shipping_option'] = $identifier;
            }

            $cart->addresses()->create($attributes);
        }

        $cart->unsetRelation('addresses');

        $lineMap = [];
        $overrides = [];
        $originalLines = $order->productLines->keyBy('id');
        $catalogPriced = array_fill_keys($request->catalogPricedLineIds, true);

        foreach ($quantities as $lineId => $quantity) {
            $source = $originalLines->get((int) $lineId);

            if (! $source) {
                continue;
            }

            $cartLine = $cart->lines()->create([
                'purchasable_id' => $source->purchasable_id,
                'purchasable_type' => $source->purchasable_type,
                'quantity' => $quantity,
                'meta' => $this->metaArray($source->meta),
            ]);

            $lineMap[$cartLine->id] = (int) $lineId;

            if (! isset($catalogPriced[(int) $lineId])) {
                $overrides[$cartLine->id] = [
                    'value' => (int) $source->unit_price->value,
                    'unit_quantity' => max(1, (int) $source->unit_quantity),
                ];
            }
        }

        $cart->load('shippingAddress', 'billingAddress', 'currency');
        $this->bindUnitPrices($cart, $overrides);

        $priced = null;
        $suggested = null;

        if ($identifier !== null) {
            $priced = ShippingManifest::getShippingOption($cart);

            if ($priced && $priced->getIdentifier() === $identifier) {
                $suggested = (int) $priced->price->value;
            }
        }

        $shippingMinor = $target->shippingMinor ?? $suggested ?? 0;
        $unavailable = $identifier !== null && $suggested === null;
        $option = null;

        if ($identifier !== null) {
            $taxClass = $priced && $priced->getIdentifier() === $identifier
                ? $priced->getTaxClass()
                : $this->shippingTaxClass($shippingLine);
            $option = new ShippingOption(
                name: $shippingLine->description ?: ($priced?->getName() ?? $identifier),
                description: $priced?->getDescription(),
                identifier: $identifier,
                price: new Price($shippingMinor, $currency, 1),
                taxClass: $taxClass,
                option: $priced?->getOption(),
                meta: $priced?->meta,
            );
            $cart->shippingOptionOverride = $option;
        }

        $applyCoupon = $request->applyCoupon && $request->couponTarget === $target->key && filled($coupon);
        $applyLoyalty = $request->applyLoyalty && $request->loyaltyTarget === $target->key;

        $cart->coupon_code = $applyCoupon ? $coupon : null;
        $cart->waiveCouponMinimumSpend = $applyCoupon;
        $cart->capFixedCouponToProductTotal = $applyCoupon;
        $cart->preserveOrderCoupon = $applyCoupon;

        $meta = [];

        if ($applyLoyalty) {
            foreach ($this->loyaltyMeta($order->meta) as $key => $value) {
                $meta[$key] = $value;
            }
        }

        $cart->meta = $meta;
        $cart->save();

        $manager = app(DiscountManagerInterface::class)->resetDiscounts();

        if ($order->channel) {
            $manager->channel($order->channel);
        }

        $this->bindUnitPrices($cart, $overrides);
        $cart->recalculate();

        return [
            'key' => $target->key,
            'cart' => $cart,
            'lineMap' => $lineMap,
            'shippingMinor' => $shippingMinor,
            'shippingPricingUnavailable' => $unavailable,
            'shippingIdentifier' => $identifier,
            'paymentOption' => $target->paymentOption,
            'paymentType' => $target->paymentType,
            'option' => $option,
        ];
    }

    /**
     * @param  array{
     *     key: string,
     *     cart: Cart,
     *     lineMap: array<int, int>,
     *     shippingMinor: int,
     *     shippingPricingUnavailable: bool,
     *     shippingIdentifier: ?string,
     *     paymentOption: string,
     *     paymentType: ?string,
     *     option: ?ShippingOption
     * }  $target
     */
    private function previewTarget(array $target): SplitOrderPreviewTarget
    {
        /** @var Cart $cart */
        $cart = $target['cart'];
        $lines = [];

        foreach ($cart->lines as $cartLine) {
            $quantity = (int) $cartLine->quantity;
            $subTotal = (int) ($cartLine->subTotal?->value ?? 0);
            $discount = (int) ($cartLine->discountTotal?->value ?? 0);
            $withoutCoupon = $cartLine->discountTotalWithoutCoupon?->value;
            $couponDiscount = $withoutCoupon === null ? 0 : max(0, $discount - (int) $withoutCoupon);
            $total = (int) ($cartLine->total?->value ?? 0);

            if ($subTotal === 0 && $discount === 0) {
                $subTotal = $total;
            }

            $lines[] = [
                'order_line_id' => $target['lineMap'][$cartLine->id] ?? 0,
                'description' => (string) ($cartLine->purchasable?->getDescription() ?? ''),
                'quantity' => $quantity,
                'unit_price' => $this->previewUnitPrice($cart, $cartLine, $quantity, $subTotal),
                'sub_total' => $subTotal,
                'discount' => $discount,
                'coupon_discount' => $couponDiscount,
                'coupon_code' => filled($cart->coupon_code) ? (string) $cart->coupon_code : null,
                'total' => $total,
            ];
        }

        return new SplitOrderPreviewTarget(
            key: $target['key'],
            shippingMinor: $target['shippingMinor'],
            shippingPricingUnavailable: $target['shippingPricingUnavailable'],
            subTotal: (int) ($cart->subTotal?->value ?? 0),
            discountTotal: (int) ($cart->discountTotal?->value ?? 0),
            shippingTotal: (int) ($cart->shippingTotal?->value ?? $target['shippingMinor']),
            taxTotal: (int) ($cart->taxTotal?->value ?? 0),
            total: (int) ($cart->total?->value ?? 0),
            lines: $lines,
            loyaltyPoints: (int) ($this->metaArray($cart->meta)['loyalty_points_to_redeem'] ?? 0),
        );
    }

    /**
     * Per-quantity catalog price in minor units, or null when this purchasable has no price.
     */
    public function catalogUnitPrice(Order $order, OrderLine $line, int $quantity): ?int
    {
        $purchasable = $line->purchasable;
        $currency = $order->currency;

        if (! $purchasable || ! $currency || $quantity < 1) {
            return null;
        }

        if ($customer = $order->customer) {
            $customerGroups = $customer->customerGroups;
        } else {
            $customerGroups = $order->user?->customers->pluck('customerGroups')->flatten();
        }

        try {
            $priceResponse = Pricing::currency($currency)
                ->qty($quantity)
                ->customerGroups($customerGroups)
                ->for($purchasable)
                ->get();
        } catch (Throwable) {
            return null;
        }

        $unitPrice = new Price(
            (int) $priceResponse->matched->price->value,
            $currency,
            $purchasable->getUnitQuantity()
        );

        return (int) round(
            $unitPrice->unitDecimal(false) * $currency->factor,
            $currency->decimal_places
        );
    }

    /**
     * Keep the same cart instance on each line so a unit price override survives calculation.
     *
     * @param  array<int, array{value: int, unit_quantity: int}>  $overrides
     */
    private function bindUnitPrices(Cart $cart, array $overrides): void
    {
        $cart->unsetRelation('lines');
        $cart->load('lines.purchasable');

        foreach ($cart->lines as $cartLine) {
            $cartLine->setRelation('cart', $cart);
        }

        $cart->unitPriceOverrides = $overrides;
    }

    /**
     * Per-quantity price before discounts, in minor units.
     */
    private function previewUnitPrice(Cart $cart, CartLine $cartLine, int $quantity, int $subTotal): int
    {
        if ($cartLine->unitPrice instanceof Price && $cart->currency) {
            return (int) round(
                $cartLine->unitPrice->unitDecimal(false) * $cart->currency->factor,
                $cart->currency->decimal_places
            );
        }

        if ($quantity > 0) {
            return (int) round($subTotal / $quantity);
        }

        return 0;
    }

    /**
     * @param  list<array{
     *     key: string,
     *     cart: Cart,
     *     lineMap: array<int, int>,
     *     shippingMinor: int,
     *     shippingPricingUnavailable: bool,
     *     shippingIdentifier: ?string,
     *     paymentOption: string,
     *     paymentType: ?string,
     *     option: ?ShippingOption
     * }>  $calculated
     * @return Collection<string, Order>
     */
    private function persist(Order $original, SplitOrderRequest $request, array $calculated): Collection
    {
        /** @var Collection<string, Order> $created */
        $created = collect();
        $originalLines = $original->productLines->keyBy('id');

        foreach ($calculated as $target) {
            /** @var Cart $cart */
            $cart = $target['cart'];
            $meta = [];

            foreach (self::COPIED_META_KEYS as $key) {
                if (array_key_exists($key, $this->metaArray($original->meta))) {
                    $meta[$key] = $this->metaArray($original->meta)[$key];
                }
            }

            $meta['payment_option'] = $target['paymentOption'];
            $meta['payment_type'] = $target['paymentType'];
            $meta['split_from_order_id'] = $original->id;

            if ($request->applyLoyalty && $request->loyaltyTarget === $target['key']) {
                foreach ($this->loyaltyMeta($cart->meta) as $key => $value) {
                    $meta[$key] = $value;
                }
            }

            $order = new Order([
                'customer_id' => $original->customer_id,
                'user_id' => $original->user_id,
                'channel_id' => $original->channel_id,
                'new_customer' => false,
                'status' => $this->initialStatus($target['paymentOption']),
                'sub_total' => 0,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => 0,
                'notes' => $original->notes,
                'currency_code' => $original->currency_code,
                'compare_currency_code' => $original->compare_currency_code,
                'exchange_rate' => $original->exchange_rate,
                'placed_at' => now(),
                'cart_id' => $cart->id,
                'meta' => $meta,
            ]);
            $order->save();

            foreach ($cart->addresses as $address) {
                $order->addresses()->create($this->addressAttributes($address));
            }

            $writtenLines = $this->writeProductLines($order, $cart, $originalLines, $target['lineMap']);
            $this->writeShippingLine($order, $cart, $target['option'], $target['shippingMinor']);

            $order->unsetRelation('lines');
            $order->load('lines');

            $generator = app(config('lunar.orders.reference_generator'));

            $order->update([
                'sub_total' => $cart->subTotal?->value ?? 0,
                'discount_total' => $cart->discountTotal?->value ?? 0,
                'discount_breakdown' => $this->discountBreakdownFromCart($cart, $writtenLines),
                'shipping_total' => $cart->shippingTotal?->value ?? $target['shippingMinor'],
                'shipping_breakdown' => $cart->shippingBreakdown ?? new ShippingBreakdown,
                'tax_breakdown' => $cart->taxBreakdown ?? new TaxBreakdown,
                'tax_total' => $cart->taxTotal?->value ?? 0,
                'total' => $cart->total?->value ?? 0,
                'fingerprint' => $cart->fingerprint(),
                'reference' => $generator->generate($order),
            ]);

            $cart->order_id = $order->id;
            $cart->save();

            $created->put($target['key'], $order->refresh());
        }

        return $created;
    }

    /**
     * @param  Collection<int, OrderLine>  $originalLines
     * @param  array<int, int>  $lineMap
     * @return array<int, OrderLine>
     */
    private function writeProductLines(Order $order, Cart $cart, Collection $originalLines, array $lineMap): array
    {
        $written = [];

        foreach ($cart->lines as $cartLine) {
            $source = $originalLines->get($lineMap[$cartLine->id] ?? 0);
            $purchasable = $cartLine->purchasable;

            $orderLine = new OrderLine([
                'order_id' => $order->id,
                'purchasable_type' => $cartLine->purchasable_type,
                'purchasable_id' => $cartLine->purchasable_id,
                'type' => $purchasable?->getType() ?? $source?->type ?? 'physical',
                'description' => $purchasable?->getDescription() ?? $source?->description,
                'option' => $purchasable?->getOption() ?? $source?->option,
                'identifier' => $purchasable?->getIdentifier() ?? $source?->identifier,
                'unit_price' => $cartLine->unitPrice?->value ?? 0,
                'unit_quantity' => $purchasable?->getUnitQuantity() ?? $source?->unit_quantity ?? 1,
                'quantity' => $cartLine->quantity,
                'sub_total' => $cartLine->subTotal?->value ?? 0,
                'discount_total' => $cartLine->discountTotal?->value ?? 0,
                'tax_breakdown' => $cartLine->taxBreakdown ?? new TaxBreakdown,
                'tax_total' => $cartLine->taxAmount?->value ?? 0,
                'total' => $cartLine->total?->value ?? 0,
                'notes' => $source?->notes,
                'meta' => $this->metaArray($cartLine->meta),
            ]);
            $orderLine->save();

            $written[$cartLine->id] = $orderLine;
        }

        return $written;
    }

    /**
     * Tax class recorded on the original shipping line.
     *
     * The breakdown stores `tax_rate_{id}` plus the percentage. That pair is the class
     * the original shipping was taxed with. A method that cannot be priced for this
     * target keeps that class instead of whichever tax class happens to be first.
     */
    private function shippingTaxClass(?OrderLine $shippingLine): TaxClass
    {
        foreach ($shippingLine?->tax_breakdown?->amounts ?? [] as $amount) {
            if (! preg_match('/^tax_rate_(\d+)$/', (string) $amount->identifier, $matches)) {
                continue;
            }

            $matched = TaxRateAmount::query()
                ->where('tax_rate_id', (int) $matches[1])
                ->whereNotNull('tax_class_id')
                ->orderBy('id')
                ->get()
                ->first(function (TaxRateAmount $rateAmount) use ($amount): bool {
                    return abs((float) $rateAmount->percentage - (float) $amount->percentage) < 0.001;
                });

            if ($matched?->taxClass) {
                return $matched->taxClass;
            }
        }

        return TaxClass::getDefault() ?? TaxClass::query()->firstOrFail();
    }

    private function writeShippingLine(Order $order, Cart $cart, ?ShippingOption $option, int $shippingMinor): void
    {
        if (! $option) {
            return;
        }

        $shippingTax = Taxes::setShippingAddress($cart->shippingAddress)
            ->setCurrency($cart->currency)
            ->setPurchasable($option)
            ->getBreakdown($shippingMinor);

        $taxTotal = (int) $shippingTax->amounts->sum('price.value');
        $total = prices_inc_tax() ? $shippingMinor : $shippingMinor + $taxTotal;

        $order->lines()->create([
            'purchasable_type' => null,
            'purchasable_id' => null,
            'type' => 'shipping',
            'description' => $option->getName(),
            'option' => $option->getOption(),
            'identifier' => $option->getIdentifier(),
            'unit_price' => $shippingMinor,
            'unit_quantity' => 1,
            'quantity' => 1,
            'sub_total' => $shippingMinor,
            'discount_total' => 0,
            'tax_breakdown' => $shippingTax,
            'tax_total' => $taxTotal,
            'total' => $total,
            'notes' => null,
            'meta' => $option->meta,
        ]);
    }

    /**
     * @param  array<int, OrderLine>  $writtenLines
     * @return Collection<int, object>
     */
    private function discountBreakdownFromCart(Cart $cart, array $writtenLines): Collection
    {
        return collect($cart->discountBreakdown ?? [])->map(function ($discount) use ($writtenLines) {
            return (object) [
                'discount_id' => $discount->discount->id,
                'lines' => collect($discount->lines)->map(function ($discountLine) use ($writtenLines) {
                    $orderLine = $writtenLines[$discountLine->line->id] ?? null;

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

    /**
     * @param  Collection<int, Order>  $created
     */
    private function markOriginalSplit(Order $original, Collection $created): void
    {
        $meta = $this->metaArray($original->meta);
        $meta['split_into_order_ids'] = $created->pluck('id')->values()->all();

        $original->meta = $meta;
        $original->status = 'split';
        $original->save();
    }

    private function initialStatus(string $paymentOption): string
    {
        if (in_array($paymentOption, self::OFFLINE_PAYMENT_OPTIONS, true)) {
            return 'payment-offline';
        }

        return 'awaiting-payment';
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

            $attributes[$field] = $address->{$field};
        }

        return $attributes;
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
