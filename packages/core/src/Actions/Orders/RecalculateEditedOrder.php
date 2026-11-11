<?php

namespace Lunar\Actions\Orders;

use Illuminate\Support\Collection;
use Lunar\Actions\Taxes\GetTaxZone;
use Lunar\Base\ValueObjects\Cart\ShippingBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdownAmount;
use Lunar\DataTypes\Price;
use Lunar\DataTypes\ShippingOption;
use Lunar\Events\OrderProductLinesRecalculated;
use Lunar\Exceptions\Orders\UnpricedOrderShippingException;
use Lunar\Facades\Taxes;
use Lunar\Models\Cart;
use Lunar\Models\CartLine;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Utils\Arr;

/**
 * Recalculate an edited order from its linked cart.
 *
 * Product-line edits and a shipping-price edit both use this. Each existing
 * product keeps the unit price stored on the order. A saved administrator
 * shipping price is applied as a cart override before the pipelines run, and
 * a later product edit keeps it. Without that flag, shipping is priced again,
 * and a free-shipping discount can set it to 0.
 */
final class RecalculateEditedOrder
{
    /**
     * @var list<string>
     */
    private const LOYALTY_META_KEYS = [
        'loyalty_points_to_redeem',
        'loyalty_discount_minor',
    ];

    /**
     * Breakdown amounts to tax from when the recorded rate can no longer be resolved.
     */
    private ?Collection $recordedShippingTaxAmounts = null;

    /**
     * Copy the order's customer, coupon, loyalty, and product lines onto the cart.
     */
    public function syncCart(Order $order, Cart $cart): void
    {
        $this->alignCart($order, $cart);
        $this->rebuildCartLines($order, $cart);
    }

    /**
     * Price the cart and copy the result back onto the order.
     *
     * A shipping-price edit keeps the loyalty redemption already stored on the order.
     */
    public function recalculate(Order $order, Cart $cart, int $previousPoints, bool $keepStoredLoyaltyRedemption = false): void
    {
        $cart->keepStoredLoyaltyRedemption = $keepStoredLoyaltyRedemption;
        $this->applyShippingPriceOverride($order, $cart);
        $this->bindStoredUnitPrices($cart);

        $cart->recalculate();

        $this->assertShippingPriced($order, $cart);
        $this->copyCalculatedOrder($order, $cart);

        event(new OrderProductLinesRecalculated($order, $previousPoints));
    }

    /**
     * Keep a saved administrator shipping price. A line without the flag is priced again.
     *
     * The override is assigned directly. setShippingOption() can reject a price the manifest did not return.
     */
    private function applyShippingPriceOverride(Order $order, Cart $cart): void
    {
        $this->recordedShippingTaxAmounts = null;
        $lines = $order->shippingLines()->get();

        if ($lines->count() !== 1) {
            return;
        }

        $line = $lines->first();
        $meta = $this->metaArray($line->meta);

        if (($meta['shipping_price_override'] ?? false) !== true) {
            return;
        }

        $recorded = $line->tax_breakdown?->amounts;

        if (! ShippingLineTaxClass::matched($line) && $recorded && $recorded->isNotEmpty()) {
            $this->recordedShippingTaxAmounts = $recorded;
        }

        if (! filled($line->identifier)) {
            return;
        }

        $currency = $cart->currency ?? $order->currency;

        if (! $currency) {
            return;
        }

        $name = filled($line->description) ? (string) $line->description : (string) $line->identifier;

        $cart->shippingOptionOverride = new ShippingOption(
            name: $name,
            description: $line->description,
            identifier: (string) $line->identifier,
            price: new Price((int) $line->unit_price->value, $currency, 1),
            taxClass: ShippingLineTaxClass::resolve($line),
            option: $line->option,
            meta: $meta,
        );
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
        $cart->unsetRelation('currency');

        $identifier = $order->shippingLines()->value('identifier');
        $this->alignShippingAddress($order, $cart, filled($identifier) ? (string) $identifier : null);
    }

    /**
     * Tax follows the shipping address on the order.
     *
     * The linked cart can still have the country from checkout after staff correct the order.
     */
    private function alignShippingAddress(Order $order, Cart $cart, ?string $identifier): void
    {
        $orderAddress = $order->shippingAddress()->first();

        if (! $orderAddress) {
            $address = $cart->shippingAddress()->first();

            if ($address && blank($address->shipping_option) && filled($identifier)) {
                $address->update([
                    'shipping_option' => $identifier,
                ]);
            }

            return;
        }

        $zone = app(GetTaxZone::class)->execute($orderAddress);

        if ($zone) {
            $cart->tax_zone_id = $zone->id;
            $cart->save();
            $cart->unsetRelation('taxZone');
        }

        $location = [
            'country_id' => $orderAddress->country_id,
            'state' => $orderAddress->state,
            'postcode' => $orderAddress->postcode,
            'city' => $orderAddress->city,
            'line_one' => $orderAddress->line_one,
            'line_two' => $orderAddress->line_two,
            'line_three' => $orderAddress->line_three,
            'title' => $orderAddress->title,
            'first_name' => $orderAddress->first_name,
            'last_name' => $orderAddress->last_name,
            'company_name' => $orderAddress->company_name,
            'contact_email' => $orderAddress->contact_email,
            'contact_phone' => $orderAddress->contact_phone,
            'delivery_instructions' => $orderAddress->delivery_instructions,
        ];

        $address = $cart->shippingAddress()->first();

        if ($address) {
            if (blank($address->shipping_option) && filled($identifier)) {
                $location['shipping_option'] = $identifier;
            }

            $address->update($location);
        } else {
            $cart->addresses()->create([
                ...$location,
                'type' => 'shipping',
                'shipping_option' => $identifier,
            ]);
        }

        $cart->unsetRelation('shippingAddress');
        $cart->unsetRelation('addresses');
    }

    private function rebuildCartLines(Order $order, Cart $cart): void
    {
        $cart->lines()->delete();
        $overrides = [];

        foreach ($order->productLines()->get() as $line) {
            $cartLine = $cart->lines()->create([
                'purchasable_id' => $line->purchasable_id,
                'purchasable_type' => $line->purchasable_type,
                'quantity' => $line->quantity,
                'meta' => $this->metaArray($line->meta),
            ]);

            $overrides[$cartLine->id] = [
                'value' => (int) $line->unit_price->value,
                'unit_quantity' => max(1, (int) $line->unit_quantity),
            ];
        }

        $cart->unitPriceOverrides = $overrides;
        $cart->unsetRelation('lines');
    }

    /**
     * Point each line at this cart so its stored unit price is used.
     *
     * A newly added product has no override and keeps the catalog price.
     */
    private function bindStoredUnitPrices(Cart $cart): void
    {
        $cart->unsetRelation('lines');
        $cart->load('lines.purchasable');

        foreach ($cart->lines as $cartLine) {
            $cartLine->setRelation('cart', $cart);
        }
    }

    private function assertShippingPriced(Order $order, Cart $cart): void
    {
        if ($cart->shippingOptionOverride) {
            return;
        }

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

        $totals = $this->orderTotals($cart);

        $order->update([
            'sub_total' => $cart->subTotal?->value ?? 0,
            'discount_total' => $cart->discountTotal?->value ?? 0,
            'discount_breakdown' => $this->discountBreakdownFromCart($order, $cart),
            'shipping_total' => $totals['shipping_total'],
            'shipping_breakdown' => $cart->shippingBreakdown ?? new ShippingBreakdown,
            'tax_breakdown' => $cart->taxBreakdown ?? new TaxBreakdown,
            'tax_total' => $totals['tax_total'],
            'total' => $totals['total'],
            'fingerprint' => $cart->fingerprint(),
            'meta' => $meta,
        ]);
    }

    /**
     * @return array{shipping_total: int, tax_total: int, total: int}
     */
    private function orderTotals(Cart $cart): array
    {
        $shippingTotal = (int) ($cart->shippingTotal?->value ?? 0);
        $taxTotal = (int) ($cart->taxTotal?->value ?? 0);
        $total = (int) ($cart->total?->value ?? 0);
        $recorded = $this->recordedShippingTax($cart, (int) ($cart->shippingSubTotal?->value ?? 0));

        if ($recorded === null) {
            return [
                'shipping_total' => $shippingTotal,
                'tax_total' => $taxTotal,
                'total' => $total,
            ];
        }

        $delta = $recorded['tax_total'] - (int) ($cart->shippingTaxTotal?->value ?? 0);
        $taxTotal += $delta;

        if (! prices_inc_tax()) {
            $shippingTotal += $delta;
            $total += $delta;
        }

        return [
            'shipping_total' => $shippingTotal,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
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

        $option = $cart->shippingOptionOverride ?: $cart->getShippingOption();

        if (! $option) {
            return;
        }

        $order->load('lines');

        $shippingLine = $order->lines->first(function (OrderLine $line) use ($option): bool {
            return $line->type === 'shipping' && $line->identifier === $option->getIdentifier();
        }) ?? new OrderLine;

        $address = $cart->shippingAddress;
        $amounts = $this->shippingAmounts($cart, $option, $address);

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
            'sub_total' => $amounts['sub_total'],
            'discount_total' => $amounts['discount_total'],
            'tax_breakdown' => $amounts['tax_breakdown'],
            'tax_total' => $amounts['tax_total'],
            'total' => $amounts['total'],
            'notes' => $shippingLine->exists ? $shippingLine->notes : null,
            'meta' => $option->meta,
        ])->save();
    }

    /**
     * Shipping line amounts from the calculated cart.
     *
     * An override is not copied onto the shipping address, so its tax comes from the same driver the cart used.
     *
     * @return array{sub_total: int, discount_total: int, tax_breakdown: TaxBreakdown, tax_total: int, total: int}
     */
    private function shippingAmounts(Cart $cart, ShippingOption $option, mixed $address): array
    {
        if (! $cart->shippingOptionOverride) {
            return [
                'sub_total' => (int) ($address?->shippingSubTotal?->value ?? $option->price->value),
                'discount_total' => (int) ($cart->shippingDiscountTotal?->value ?? 0),
                'tax_breakdown' => $address?->taxBreakdown ?? new TaxBreakdown,
                'tax_total' => (int) ($address?->shippingTaxTotal?->value ?? 0),
                'total' => (int) ($address?->shippingTotal?->value ?? $option->price->value),
            ];
        }

        $subTotal = (int) ($cart->shippingSubTotal?->value ?? $option->price->value);
        $recorded = $this->recordedShippingTax($cart, $subTotal);

        if ($recorded !== null) {
            return [
                'sub_total' => $subTotal,
                'discount_total' => (int) ($cart->shippingDiscountTotal?->value ?? 0),
                'tax_breakdown' => $recorded['breakdown'],
                'tax_total' => $recorded['tax_total'],
                'total' => $recorded['total'],
            ];
        }

        return [
            'sub_total' => $subTotal,
            'discount_total' => (int) ($cart->shippingDiscountTotal?->value ?? 0),
            'tax_breakdown' => Taxes::setShippingAddress($address)
                ->setCurrency($cart->currency)
                ->setPurchasable($option)
                ->setTaxZone($cart->taxZone)
                ->getBreakdown($subTotal),
            'tax_total' => (int) ($cart->shippingTaxTotal?->value ?? 0),
            'total' => (int) ($cart->shippingTotal?->value ?? $subTotal),
        ];
    }

    /**
     * Tax from the percentages already stored on the shipping line.
     *
     * Used when that rate was removed or its percentage no longer matches a tax class,
     * so the line does not pick up the default class.
     *
     * @return array{breakdown: TaxBreakdown, tax_total: int, total: int}|null
     */
    private function recordedShippingTax(Cart $cart, int $subTotal): ?array
    {
        if ($this->recordedShippingTaxAmounts === null || ! $cart->currency) {
            return null;
        }

        $breakdown = new TaxBreakdown;
        $taxTotal = 0;

        foreach ($this->recordedShippingTaxAmounts as $amount) {
            $part = $this->taxPart($subTotal, (float) $amount->percentage);
            $taxTotal += $part;
            $breakdown->addAmount(new TaxBreakdownAmount(
                price: new Price($part, $cart->currency, 1),
                identifier: (string) $amount->identifier,
                description: (string) $amount->description,
                percentage: (float) $amount->percentage,
            ));
        }

        return [
            'breakdown' => $breakdown,
            'tax_total' => $taxTotal,
            'total' => prices_inc_tax() ? $subTotal : $subTotal + $taxTotal,
        ];
    }

    private function taxPart(int $subTotal, float $percentage): int
    {
        if ($percentage == 0.0) {
            return 0;
        }

        if (prices_inc_tax()) {
            return (int) round($subTotal - ($subTotal / (1 + ($percentage / 100))));
        }

        return (int) round($subTotal * ($percentage / 100));
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
}
