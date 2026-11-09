<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Lunar\Actions\Orders\OrderProductLineChange;
use Lunar\Actions\Orders\UpdateOrderProductLines;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\DataTypes\Price as PriceDataType;
use Lunar\DataTypes\ShippingOption;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Exceptions\Carts\CartException;
use Lunar\Exceptions\InsufficientStockException;
use Lunar\Exceptions\NonPurchasableItemException;
use Lunar\Exceptions\Orders\LastOrderProductLineException;
use Lunar\Exceptions\Orders\LockedOrderProductLineEditException;
use Lunar\Exceptions\Orders\MissingOrderCartException;
use Lunar\Exceptions\Orders\UnauthorizedOrderProductLineEditException;
use Lunar\Exceptions\Orders\UnpricedOrderShippingException;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\Transaction;
use Lunar\Tests\Core\Stubs\User;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create([
        'code' => 'en',
        'default' => true,
    ]);

    $this->currency = Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);

    $this->channel = Channel::factory()->create([
        'default' => true,
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);
    Gate::define('sales:manage-orders', fn () => true);
});

/**
 * @param  array<string, mixed>  $orderAttributes
 * @param  array<string, mixed>|null  $lineMeta
 * @return array{order: Order, cart: Cart, variant: ProductVariant, line: OrderLine}
 */
function editableOrder(int $quantity = 1, int $price = 1000, ?array $lineMeta = null, array $orderAttributes = []): array
{
    $variant = pricedVariant($price);

    $cart = Cart::factory()->create([
        'currency_id' => test()->currency->id,
        'channel_id' => test()->channel->id,
    ]);

    $order = Order::factory()->create(array_merge([
        'channel_id' => test()->channel->id,
        'currency_code' => test()->currency->code,
        'cart_id' => $cart->id,
        'status' => 'awaiting-payment',
        'reference' => 'REF-'.str()->random(8),
        'placed_at' => now()->subDay(),
        'notes' => 'keep-notes',
        'meta' => ['payment_reference' => 'pay-1'],
        'total' => 1,
    ], $orderAttributes));

    $line = productLine($order, $variant, $quantity, $price, $lineMeta);

    return compact('order', 'cart', 'variant', 'line');
}

function pricedVariant(int $price, array $attributes = []): ProductVariant
{
    $variant = ProductVariant::factory()->create($attributes);

    Price::factory()->create([
        'price' => $price,
        'currency_id' => test()->currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    return $variant;
}

function productLine(Order $order, ProductVariant $variant, int $quantity, int $price, ?array $meta = null): OrderLine
{
    return OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'quantity' => $quantity,
        'unit_price' => $price,
        'unit_quantity' => 1,
        'sub_total' => $price * $quantity,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => $price * $quantity,
        'meta' => $meta,
    ]);
}

function enableOrderSending(): void
{
    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['magister']);
    Config::set('lunar.erp.magister.enabled', true);
    Config::set('lunar.erp.actions.send_order', ['magister']);
}

function stockStatuses(): void
{
    Config::set('lunar-frontend.orders.stock.decrease', ['prepare-shipment']);
    Config::set('lunar-frontend.orders.stock.increase', ['canceled', 'payment-failed', 'test-order']);
    Config::set('lunar.cart.stock_check.enabled', true);
}

it('rejects a captured order, an awb, an invoice, an erp timestamp, a historical send, a user who cannot manage orders, and an order with no cart', function (string $case) {
    ['order' => $order, 'line' => $line, 'cart' => $cart] = editableOrder();
    $before = $order->fresh();
    $lineQuantity = $line->quantity;
    $cartCount = $cart->lines()->count();

    match ($case) {
        'captured' => $order->transactions()->save(Transaction::factory()->make([
            'type' => 'capture',
            'success' => true,
        ])),
        'awb' => $order->update(['meta' => ['awb' => 'AWB-1', 'payment_reference' => 'pay-1']]),
        'invoice' => $order->update(['meta' => ['billing_series' => 'S', 'billing_number' => '9', 'payment_reference' => 'pay-1']]),
        'erp timestamp' => $order->update(['meta' => ['erp_sent_at' => now()->toIso8601String(), 'payment_reference' => 'pay-1']]),
        'historical send' => (function () use ($order) {
            enableOrderSending();
            $order->update(['status' => 'payment-offline']);
        })(),
        'unauthorized' => Gate::define('sales:manage-orders', fn () => false),
        'missing cart' => $order->update(['cart_id' => null]),
    };

    $exception = match ($case) {
        'unauthorized' => UnauthorizedOrderProductLineEditException::class,
        'missing cart' => MissingOrderCartException::class,
        default => LockedOrderProductLineEditException::class,
    };

    expect(fn () => UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::setQuantity($line->id, 2)))
        ->toThrow($exception);

    $order->refresh();

    expect($order->total->value)->toBe($before->total->value)
        ->and($order->lines()->find($line->id)->quantity)->toBe($lineQuantity)
        ->and($cart->fresh()->lines()->count())->toBe($cartCount);
})->with([
    'captured',
    'awb',
    'invoice',
    'erp timestamp',
    'historical send',
    'unauthorized',
    'missing cart',
]);

it('rejects a quantity the variant rules reject and removal of the last product line', function () {
    ['order' => $order, 'line' => $line, 'variant' => $variant] = editableOrder();
    $variant->update(['min_quantity' => 4]);
    $total = $order->fresh()->total->value;

    expect(fn () => UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::setQuantity($line->id, 2)))
        ->toThrow(CartException::class);

    expect(fn () => UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::setQuantity($line->id, 0)))
        ->toThrow(CartException::class);

    expect(fn () => UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::remove($line->id)))
        ->toThrow(LastOrderProductLineException::class);

    $order->refresh();

    expect($order->lines()->find($line->id)->quantity)->toBe(1)
        ->and($order->total->value)->toBe($total);
});

it('rejects a variant that is not purchasable', function () {
    ['order' => $order] = editableOrder();
    $variant = pricedVariant(500);
    $variant->product->update(['status' => 'draft']);
    $lineCount = $order->productLines()->count();

    expect(fn () => UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::add($variant, 1)))
        ->toThrow(NonPurchasableItemException::class);

    expect($order->fresh()->productLines()->count())->toBe($lineCount);
});

it('adds to an existing variant instead of creating a second line', function () {
    ['order' => $order, 'variant' => $variant, 'line' => $line] = editableOrder(quantity: 2);

    $saved = UpdateOrderProductLines::run($order, OrderProductLineChange::add($variant->fresh(), 3));

    expect($saved->productLines()->count())->toBe(1)
        ->and($saved->productLines()->first()->id)->toBe($line->id)
        ->and($saved->productLines()->first()->quantity)->toBe(5);
});

it('returns stock for a removed line and a lower quantity, takes stock for a rise, and rejects a rise the variant cannot fulfill', function () {
    stockStatuses();

    ['order' => $order, 'variant' => $variant, 'line' => $line] = editableOrder(quantity: 4, price: 1000);
    $other = pricedVariant(500, ['purchasable' => 'in_stock', 'stock' => 7]);
    $variant->update(['purchasable' => 'in_stock', 'stock' => 6]);
    productLine($order, $other, 1, 500);

    activity()->enableLogging();
    $order->update(['status' => 'prepare-shipment']);

    UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::setQuantity($line->id, 2));

    expect($variant->fresh()->stock)->toBe(8);

    UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::remove($line->id));

    expect($variant->fresh()->stock)->toBe(10)
        ->and($order->fresh()->productLines()->whereKey($line->id)->exists())->toBeFalse();

    $rise = editableOrder(quantity: 2, price: 1000);
    $rise['variant']->update(['purchasable' => 'in_stock', 'stock' => 5]);
    activity()->enableLogging();
    $rise['order']->update(['status' => 'prepare-shipment']);

    UpdateOrderProductLines::run($rise['order']->fresh(), OrderProductLineChange::setQuantity($rise['line']->id, 4));

    expect($rise['variant']->fresh()->stock)->toBe(3);

    $blocked = editableOrder(quantity: 2, price: 1000);
    $blocked['variant']->update(['purchasable' => 'in_stock', 'stock' => 1]);
    activity()->enableLogging();
    $blocked['order']->update(['status' => 'prepare-shipment']);

    expect(fn () => UpdateOrderProductLines::run($blocked['order']->fresh(), OrderProductLineChange::setQuantity($blocked['line']->id, 5)))
        ->toThrow(Exception::class);

    expect($blocked['variant']->fresh()->stock)->toBe(1)
        ->and($blocked['line']->fresh()->quantity)->toBe(2);
});

it('rejects an unfulfillable increase when the storefront stock check is off', function () {
    stockStatuses();
    Config::set('lunar.cart.stock_check.enabled', false);

    ['order' => $order, 'variant' => $variant, 'line' => $line] = editableOrder(quantity: 2, price: 1000);
    $variant->update(['purchasable' => 'in_stock', 'stock' => 1]);
    activity()->enableLogging();
    $order->update(['status' => 'prepare-shipment']);

    expect(fn () => UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::setQuantity($line->id, 5)))
        ->toThrow(InsufficientStockException::class);

    expect($variant->fresh()->stock)->toBe(1)
        ->and($line->fresh()->quantity)->toBe(2);
});

it('leaves stock unchanged when the order has no stock activity or a later cancel restored it', function () {
    stockStatuses();

    ['order' => $order, 'variant' => $variant, 'line' => $line] = editableOrder(quantity: 2);
    $second = pricedVariant(400, ['stock' => 9]);
    $variant->update(['stock' => 9]);
    productLine($order, $second, 1, 400);

    UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::remove($line->id));

    expect($variant->fresh()->stock)->toBe(9);

    $restored = editableOrder(quantity: 2);
    $extra = pricedVariant(400);
    $restored['variant']->update(['stock' => 4]);
    productLine($restored['order'], $extra, 1, 400);
    activity()->enableLogging();
    $restored['order']->update(['status' => 'prepare-shipment']);
    $restored['order']->update(['status' => 'canceled']);

    UpdateOrderProductLines::run($restored['order']->fresh(), OrderProductLineChange::remove($restored['line']->id));

    expect($restored['variant']->fresh()->stock)->toBe(4);
});

it('stores the recalculated quantity and total and keeps status, reference, and personalization', function () {
    ['order' => $order, 'line' => $line] = editableOrder(quantity: 1, price: 2500, lineMeta: ['personalization' => 'Ada']);
    $reference = $order->reference;
    $status = $order->status;
    $placedAt = $order->placed_at->toDateTimeString();

    $saved = UpdateOrderProductLines::run($order, OrderProductLineChange::setQuantity($line->id, 3));
    $savedLine = $saved->productLines()->first();
    $calculated = $saved->cart->fresh()->recalculate();

    expect($savedLine->quantity)->toBe(3)
        ->and($saved->total->value)->toBe($calculated->total->value)
        ->and($savedLine->total->value)->toBe($calculated->lines->first()->total->value)
        ->and($saved->status)->toBe($status)
        ->and($saved->reference)->toBe($reference)
        ->and($saved->placed_at->toDateTimeString())->toBe($placedAt)
        ->and($saved->notes)->toBe('keep-notes')
        ->and($saved->meta['payment_reference'])->toBe('pay-1')
        ->and($savedLine->meta['personalization'])->toBe('Ada')
        ->and($saved->fingerprint)->toBe($calculated->fingerprint());
});

it('leaves the order and stock unchanged when the shipping option cannot be priced', function () {
    stockStatuses();

    ['order' => $order, 'cart' => $cart, 'variant' => $variant, 'line' => $line] = editableOrder(quantity: 2, price: 1000);
    $variant->update(['purchasable' => 'in_stock', 'stock' => 10]);
    activity()->enableLogging();
    $order->update(['status' => 'prepare-shipment']);

    $order->lines()->create([
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'description' => 'Courier',
        'identifier' => 'missing-rate',
        'unit_price' => 500,
        'unit_quantity' => 1,
        'quantity' => 1,
        'sub_total' => 500,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 500,
    ]);

    CartAddress::factory()->create([
        'cart_id' => $cart->id,
        'type' => 'shipping',
        'shipping_option' => null,
    ]);

    expect(fn () => UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::setQuantity($line->id, 3)))
        ->toThrow(UnpricedOrderShippingException::class);

    expect($line->fresh()->quantity)->toBe(2)
        ->and($variant->fresh()->stock)->toBe(10)
        ->and($order->fresh()->total->value)->toBe(1);
});

it('merges an added preorder variant with the existing preorder line', function () {
    ['order' => $order, 'variant' => $variant, 'line' => $line] = editableOrder(
        quantity: 1,
        lineMeta: ['preorder' => true],
    );
    $variant->update(['purchasable' => 'preorder']);

    $saved = UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::add($variant->fresh(), 2));
    $updated = $saved->productLines()->first();

    expect($saved->productLines()->count())->toBe(1)
        ->and($updated->id)->toBe($line->id)
        ->and($updated->quantity)->toBe(3)
        ->and($updated->meta['preorder'])->toBeTrue();
});

it('keeps the preorder record and consultation timestamp when the quantity changes', function () {
    $completedAt = '2026-10-01T10:00:00+00:00';
    ['order' => $order, 'line' => $line] = editableOrder(
        quantity: 1,
        lineMeta: [
            'preorder' => true,
            'preorder_consultation_completed_at' => $completedAt,
        ],
    );

    $saved = UpdateOrderProductLines::run($order, OrderProductLineChange::setQuantity($line->id, 4));
    $updated = $saved->productLines()->first();

    expect($updated->quantity)->toBe(4)
        ->and($updated->meta['preorder'])->toBeTrue()
        ->and($updated->meta['preorder_consultation_completed_at'])->toBe($completedAt)
        ->and($saved->awaitsPreorderConsultation())->toBeFalse();
});

it('keeps the consultation and the status when a new preorder line is added', function () {
    $completedAt = '2026-10-01T10:00:00+00:00';
    ['order' => $order, 'line' => $line] = editableOrder(
        lineMeta: [
            'preorder' => true,
            'preorder_consultation_completed_at' => $completedAt,
        ],
    );
    $status = $order->status;
    $added = pricedVariant(500, ['purchasable' => 'preorder']);

    $saved = UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::add($added, 1));
    $existing = $saved->productLines()->whereKey($line->id)->first();
    $newLine = $saved->productLines()->where('purchasable_id', $added->id)->first();

    expect($saved->status)->toBe($status)
        ->and($saved->awaitsPreorderConsultation())->toBeFalse()
        ->and($newLine->meta['preorder'])->toBeTrue()
        ->and($newLine->meta['preorder_consultation_completed_at'])->toBe($completedAt)
        ->and($existing->meta['preorder'])->toBeTrue()
        ->and($existing->meta['preorder_consultation_completed_at'])->toBe($completedAt);
});

it('still waits for consultation when a preorder line is added before it is complete', function () {
    ['order' => $order, 'line' => $line] = editableOrder(
        lineMeta: ['preorder' => true],
    );
    $status = $order->status;
    $added = pricedVariant(500, ['purchasable' => 'preorder']);

    $saved = UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::add($added, 1));
    $existing = $saved->productLines()->whereKey($line->id)->first();
    $newLine = $saved->productLines()->where('purchasable_id', $added->id)->first();

    expect($saved->status)->toBe($status)
        ->and($saved->awaitsPreorderConsultation())->toBeTrue()
        ->and($newLine->meta['preorder'])->toBeTrue()
        ->and($newLine->meta['preorder_consultation_completed_at'] ?? null)->toBeNull()
        ->and($existing->meta['preorder_consultation_completed_at'] ?? null)->toBeNull();
});

/**
 * Give the order a 19.90 courier shipping line that was placed free.
 */
function freeShippingCourierLine(Order $order, Cart $cart): void
{
    ShippingManifest::addOption(new ShippingOption(
        name: 'Courier',
        description: 'Courier',
        identifier: 'COURIER',
        price: new PriceDataType(1990, test()->currency, 1),
        taxClass: TaxClass::factory()->create(),
    ));

    CartAddress::factory()->create([
        'cart_id' => $cart->id,
        'type' => 'shipping',
        'shipping_option' => 'COURIER',
    ]);

    $order->lines()->create([
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'description' => 'Courier',
        'identifier' => 'COURIER',
        'unit_price' => 1990,
        'unit_quantity' => 1,
        'quantity' => 1,
        'sub_total' => 0,
        'discount_total' => 1990,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 0,
    ]);
}

/**
 * @param  array<string, mixed>  $data
 */
function freeShippingEditDiscount(?string $coupon, array $data = []): Discount
{
    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => $coupon,
        'starts_at' => now()->subHour(),
        'data' => array_merge([
            'fixed_value' => false,
            'percentage' => null,
            'free_shipping' => true,
        ], $data),
    ]);

    $discount->channels()->sync([
        test()->channel->id => ['enabled' => true, 'starts_at' => now()->subHour()],
    ]);

    return $discount;
}

it('keeps shipping free with the waived amount on the line while the free shipping coupon still qualifies', function () {
    ['order' => $order, 'cart' => $cart, 'line' => $line] = editableOrder(quantity: 2, price: 1000);
    freeShippingCourierLine($order, $cart);
    $cart->update(['coupon_code' => 'SHIPFREE']);
    freeShippingEditDiscount('SHIPFREE', ['min_prices' => [test()->currency->code => 1500]]);

    $saved = UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::setQuantity($line->id, 3));
    $shippingLine = $saved->shippingLines()->first();

    expect($saved->shipping_total->value)->toBe(0)
        ->and($saved->shipping_discount_total->value)->toBe(1990)
        ->and($saved->appliedCoupon?->coupon)->toBe('SHIPFREE')
        ->and($shippingLine->unit_price->value)->toBe(1990)
        ->and($shippingLine->discount_total->value)->toBe(1990)
        ->and($shippingLine->sub_total->value)->toBe(0)
        ->and($shippingLine->tax_total->value)->toBe(0)
        ->and($shippingLine->total->value)->toBe(0)
        ->and($saved->total->value)->toBe($saved->sub_total->value + $saved->tax_total->value);
});

it('charges full shipping when the free shipping coupon no longer meets its minimum cart amount', function () {
    ['order' => $order, 'cart' => $cart, 'line' => $line] = editableOrder(quantity: 2, price: 1000);
    freeShippingCourierLine($order, $cart);
    $cart->update(['coupon_code' => 'SHIPFREE']);
    freeShippingEditDiscount('SHIPFREE', ['min_prices' => [test()->currency->code => 1500]]);

    $saved = UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::setQuantity($line->id, 1));
    $shippingLine = $saved->shippingLines()->first();

    expect($shippingLine->discount_total->value)->toBe(0)
        ->and($shippingLine->sub_total->value)->toBe(1990)
        ->and($saved->shipping_total->value)->toBe($shippingLine->total->value)
        ->and($saved->shipping_total->value)->toBeGreaterThanOrEqual(1990)
        ->and($saved->shipping_discount_total->value)->toBe(0)
        ->and($saved->total->value)->toBe(1000 + $saved->shipping_total->value + $saved->productLines()->sum('tax_total'));
});

it('charges full shipping when the only line eligible for an automatic free shipping discount is removed', function () {
    ['order' => $order, 'cart' => $cart, 'line' => $line, 'variant' => $variant] = editableOrder(quantity: 1, price: 1000);
    $otherLine = productLine($order, pricedVariant(500), 1, 500);
    freeShippingCourierLine($order, $cart);

    $collection = Collection::factory()->create();
    $variant->product->collections()->attach($collection->id);

    $discount = freeShippingEditDiscount(null);
    $discount->collections()->attach($collection->id, ['type' => 'limitation']);

    $kept = UpdateOrderProductLines::run($order->fresh(), OrderProductLineChange::setQuantity($otherLine->id, 2));

    expect($kept->shipping_total->value)->toBe(0)
        ->and($kept->shipping_discount_total->value)->toBe(1990);

    $saved = UpdateOrderProductLines::run($kept->fresh(), OrderProductLineChange::remove($line->id));
    $shippingLine = $saved->shippingLines()->first();

    expect($saved->productLines()->count())->toBe(1)
        ->and($shippingLine->discount_total->value)->toBe(0)
        ->and($shippingLine->sub_total->value)->toBe(1990)
        ->and($saved->shipping_total->value)->toBe($shippingLine->total->value)
        ->and($saved->shipping_total->value)->toBeGreaterThanOrEqual(1990)
        ->and($saved->shipping_discount_total->value)->toBe(0);
});
