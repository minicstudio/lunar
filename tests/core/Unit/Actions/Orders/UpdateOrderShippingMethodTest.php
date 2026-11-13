<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lunar\Actions\Orders\OrderProductLineChange;
use Lunar\Actions\Orders\UpdateOrderProductLines;
use Lunar\Actions\Orders\UpdateOrderShippingMethod;
use Lunar\Actions\Orders\UpdateOrderShippingPrice;
use Lunar\Base\ShippingModifiers;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Exceptions\Orders\InvalidOrderShippingMethodException;
use Lunar\Exceptions\Orders\LockedOrderProductLineEditException;
use Lunar\Exceptions\Orders\MissingOrderCartException;
use Lunar\Exceptions\Orders\UnauthorizedOrderProductLineEditException;
use Lunar\Exceptions\Orders\UnpricedOrderShippingException;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Lunar\Models\TaxZoneCountry;
use Lunar\Models\Transaction;
use Lunar\Tests\Core\Stubs\OrderShippingMethodModifier;
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

    $this->actingAs(User::factory()->create());
    Gate::define('sales:manage-orders', fn () => true);

    $taxClass = TaxClass::factory()->create(['default' => true]);
    $this->taxZone = TaxZone::factory()->create([
        'default' => true,
        'active' => true,
        'zone_type' => 'country',
    ]);
    $taxRate = TaxRate::factory()->create([
        'tax_zone_id' => $this->taxZone->id,
    ]);

    TaxRateAmount::factory()->create([
        'percentage' => 19,
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    $this->country = Country::factory()->create();
    TaxZoneCountry::factory()->create([
        'tax_zone_id' => $this->taxZone->id,
        'country_id' => $this->country->id,
    ]);

    app(ShippingModifiers::class)->remove(OrderShippingMethodModifier::class);
    app(ShippingModifiers::class)->add(OrderShippingMethodModifier::class);
    OrderShippingMethodModifier::reset();
});

afterEach(function () {
    app(ShippingModifiers::class)->remove(OrderShippingMethodModifier::class);
    OrderShippingMethodModifier::reset();
    ShippingManifest::clearOptions();
});

/**
 * @param  list<array{identifier: string, name: string, price: int, collect?: bool, option?: ?string, when_postcode?: ?string}>  $options
 */
function offerShippingMethods(array $options, array $pricesByPostcode = [], bool $requireKnownPostcode = false): void
{
    OrderShippingMethodModifier::$options = array_map(fn (array $option): array => [
        'identifier' => $option['identifier'],
        'name' => $option['name'],
        'price' => $option['price'],
        'collect' => $option['collect'] ?? false,
        'option' => $option['option'] ?? null,
        'when_postcode' => $option['when_postcode'] ?? null,
    ], $options);
    OrderShippingMethodModifier::$pricesByPostcode = $pricesByPostcode;
    OrderShippingMethodModifier::$requireKnownPostcode = $requireKnownPostcode;
}

/**
 * @param  array<string, mixed>  $shippingMeta
 * @param  array<string, mixed>  $orderMeta
 * @return array{order: Order, cart: Cart, variant: ProductVariant, line: OrderLine, shipping: OrderLine}
 */
function shippingMethodOrder(
    string $identifier = 'PICKUP',
    string $name = 'Personal pickup',
    int $shippingMinor = 0,
    array $shippingMeta = [],
    array $orderMeta = [],
    int $stock = 8,
    bool $withBilling = false,
): array {
    $variant = ProductVariant::factory()->create(['stock' => $stock]);

    Price::factory()->create([
        'price' => 1000,
        'currency_id' => test()->currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => test()->currency->id,
        'channel_id' => test()->channel->id,
    ]);

    $order = Order::factory()->create([
        'channel_id' => test()->channel->id,
        'currency_code' => test()->currency->code,
        'cart_id' => $cart->id,
        'status' => 'awaiting-payment',
        'reference' => 'REF-'.str()->random(8),
        'placed_at' => now()->subDay(),
        'notes' => 'keep-notes',
        'meta' => array_merge(['payment_reference' => 'pay-1'], $orderMeta),
        'total' => 1,
        'shipping_total' => $shippingMinor,
    ]);

    $line = OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'quantity' => 1,
        'unit_price' => 1000,
        'unit_quantity' => 1,
        'sub_total' => 1000,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 1000,
    ]);

    $shipping = OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'description' => $name,
        'identifier' => $identifier,
        'option' => 'Old zone',
        'unit_price' => $shippingMinor,
        'unit_quantity' => 1,
        'quantity' => 1,
        'sub_total' => $shippingMinor,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => $shippingMinor,
        'meta' => $shippingMeta,
    ]);

    CartAddress::factory()->create([
        'cart_id' => $cart->id,
        'type' => 'shipping',
        'shipping_option' => $identifier,
        'postcode' => 'STORE',
        'line_one' => 'Store Counter',
    ]);

    if ($withBilling) {
        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'type' => 'billing',
            'country_id' => test()->country->id,
            'state' => null,
            'line_one' => '10 Main Street',
            'postcode' => 'BILL1',
            'city' => 'London',
        ]);
    }

    return compact('order', 'cart', 'variant', 'line', 'shipping');
}

it('replaces personal pickup with a courier and moves the order total with the shipping total', function (bool $pricesIncludeTax) {
    Config::set('lunar.pricing.stored_inclusive_of_tax', $pricesIncludeTax);

    ['order' => $order, 'variant' => $variant] = shippingMethodOrder(
        orderMeta: ['shippingType' => 'pickup'],
        withBilling: true,
    );

    $variant->update([
        'tax_class_id' => TaxClass::getDefault()->id,
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'country_id' => test()->country->id,
        'state' => null,
        'line_one' => 'Store Counter',
        'postcode' => 'STORE',
        'shipping_option' => 'PICKUP',
    ]);

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500, 'option' => 'National'],
    ]);

    $normalized = UpdateOrderShippingPrice::run($order, 0);
    $beforeShipping = $normalized->shipping_total->value;
    $beforeTotal = $normalized->total->value;

    $saved = UpdateOrderShippingMethod::run($normalized, 'COURIER');
    $shippingLine = $saved->shippingLines()->first();
    $shippingDifference = $saved->shipping_total->value - $beforeShipping;

    expect($saved->shippingLines()->count())->toBe(1)
        ->and($shippingLine->identifier)->toBe('COURIER')
        ->and($shippingLine->description)->toBe('Courier')
        ->and($shippingLine->option)->toBe('National')
        ->and($shippingLine->unit_price->value)->toBe(1500)
        ->and($shippingLine->sub_total->value)->toBe(1500)
        ->and($saved->total->value - $beforeTotal)->toBe($shippingDifference);

    if ($pricesIncludeTax) {
        expect($saved->shipping_total->value)->toBe(1500);

        return;
    }

    expect($saved->shipping_total->value)->toBe($shippingLine->sub_total->value + $shippingLine->tax_total->value)
        ->and($shippingLine->tax_total->value)->toBe(285)
        ->and($saved->shipping_total->value)->toBe(1785);
})->with([
    'prices include tax' => true,
    'prices exclude tax' => false,
]);

it('prices a method from the product line subtotal', function () {
    ['order' => $order] = shippingMethodOrder(
        orderMeta: ['shippingType' => 'pickup'],
    );

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 100],
    ]);
    OrderShippingMethodModifier::$priceByMinimumSubtotal = [
        0 => 100,
        1 => 1500,
    ];

    $quoted = UpdateOrderShippingMethod::make()
        ->offeredOptions($order->fresh())
        ->first(fn ($option) => $option->getIdentifier() === 'COURIER');

    $saved = UpdateOrderShippingMethod::run($order->fresh(), 'COURIER');
    $shippingLine = $saved->shippingLines()->first();

    expect($quoted?->price->value)->toBe(1500)
        ->and($shippingLine->identifier)->toBe('COURIER')
        ->and($shippingLine->unit_price->value)->toBe(1500)
        ->and($shippingLine->sub_total->value)->toBe(1500);
});

it('stores a collection option with a shipping subtotal of 0', function () {
    ['order' => $order] = shippingMethodOrder(
        identifier: 'COURIER',
        name: 'Courier',
        shippingMinor: 1500,
        orderMeta: ['shippingType' => 'courier'],
    );

    offerShippingMethods([
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500],
        ['identifier' => 'COLLECT', 'name' => 'Collection', 'price' => 0, 'collect' => true, 'option' => 'Store'],
    ]);

    $saved = UpdateOrderShippingMethod::run($order, 'COLLECT');
    $shippingLine = $saved->shippingLines()->first();

    expect($shippingLine->description)->toBe('Collection')
        ->and($shippingLine->identifier)->toBe('COLLECT')
        ->and($shippingLine->sub_total->value)->toBe(0)
        ->and($saved->shippingLines()->count())->toBe(1);
});

it('clears a saved shipping price and a later product edit keeps the new method', function () {
    ['order' => $order, 'line' => $line, 'shipping' => $shipping] = shippingMethodOrder(
        identifier: 'PICKUP',
        shippingMinor: 999,
        shippingMeta: ['shipping_price_override' => true],
        orderMeta: ['shippingType' => 'pickup'],
        withBilling: true,
    );

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500, 'option' => 'National'],
    ]);

    $saved = UpdateOrderShippingMethod::run($order, 'COURIER');
    $shippingLine = $saved->shippingLines()->first();

    expect($shippingLine->id)->toBe($shipping->id)
        ->and($shippingLine->sub_total->value)->toBe(1500)
        ->and(data_get($shippingLine->meta, 'shipping_price_override'))->toBeNull();

    $edited = UpdateOrderProductLines::run($saved, OrderProductLineChange::setQuantity($line->id, 2));
    $repriced = $edited->shippingLines()->first();

    expect($edited->productLines()->first()->quantity)->toBe(2)
        ->and($repriced->identifier)->toBe('COURIER')
        ->and($repriced->sub_total->value)->toBe(1500)
        ->and(data_get($repriced->meta, 'shipping_price_override'))->toBeNull();
});

it('lets a qualifying free-shipping coupon waive the new method', function () {
    ['order' => $order, 'cart' => $cart] = shippingMethodOrder(
        identifier: 'PICKUP',
        orderMeta: ['shippingType' => 'pickup'],
        withBilling: true,
    );

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1200],
    ]);

    $cart->update(['coupon_code' => 'SHIPFREE']);

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'SHIPFREE',
        'starts_at' => now()->subHour(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
            'free_shipping' => true,
        ],
    ]);
    $discount->channels()->sync([
        test()->channel->id => ['enabled' => true, 'starts_at' => now()->subHour()],
    ]);

    $saved = UpdateOrderShippingMethod::run($order->fresh(), 'COURIER');
    $shippingLine = $saved->shippingLines()->first();

    expect($shippingLine->unit_price->value)->toBe(1200)
        ->and($shippingLine->discount_total->value)->toBe(1200)
        ->and($shippingLine->sub_total->value)->toBe(0)
        ->and($shippingLine->tax_total->value)->toBe(0)
        ->and($shippingLine->total->value)->toBe(0)
        ->and($saved->shipping_total->value)->toBe(0)
        ->and($saved->productLines()->first()->discount_total->value)->toBeGreaterThan(0);
});

it('keeps the saved price when the current method is submitted again', function () {
    ['order' => $order, 'shipping' => $shipping] = shippingMethodOrder(
        identifier: 'COURIER',
        name: 'Courier',
        shippingMinor: 999,
        shippingMeta: ['shipping_price_override' => true],
        orderMeta: ['shippingType' => 'courier'],
    );

    offerShippingMethods([
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500],
    ]);

    expect(fn () => UpdateOrderShippingMethod::run($order, 'COURIER'))
        ->toThrow(fn (InvalidOrderShippingMethodException $exception) => expect($exception->reasons)->toBe(['shipping_method_unchanged']));

    $line = $shipping->fresh();

    expect($line->identifier)->toBe('COURIER')
        ->and($line->unit_price->value)->toBe(999)
        ->and($line->meta['shipping_price_override'])->toBeTrue();
});

it('leaves the order unchanged when the method cannot be saved', function (string $case) {
    ['order' => $order, 'shipping' => $shipping, 'cart' => $cart, 'variant' => $variant] = shippingMethodOrder(
        identifier: 'COURIER',
        name: 'Courier',
        shippingMinor: 200,
        orderMeta: ['shippingType' => 'courier'],
    );
    $beforeTotal = $order->fresh()->total->value;
    $beforeIdentifier = $shipping->identifier;
    $stock = $variant->stock;

    offerShippingMethods([
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 200],
        ['identifier' => 'EXPRESS', 'name' => 'Express', 'price' => 1500],
    ]);

    $identifier = 'EXPRESS';

    match ($case) {
        'unoffered' => $identifier = 'OTHER',
        'unpriced' => $cart->addresses()->delete(),
        'captured' => $order->transactions()->save(Transaction::factory()->make([
            'type' => 'capture',
            'success' => true,
        ])),
        'unauthorized' => Gate::define('sales:manage-orders', fn () => false),
        'missing cart' => $order->update(['cart_id' => null]),
        'no shipping line' => $shipping->delete(),
        'two shipping lines' => $order->lines()->create([
            'purchasable_type' => null,
            'purchasable_id' => null,
            'type' => 'shipping',
            'description' => 'Second',
            'identifier' => 'SECOND',
            'unit_price' => 100,
            'unit_quantity' => 1,
            'quantity' => 1,
            'sub_total' => 100,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 100,
        ]),
    };

    $exception = match ($case) {
        'captured' => LockedOrderProductLineEditException::class,
        'unauthorized' => UnauthorizedOrderProductLineEditException::class,
        'missing cart' => MissingOrderCartException::class,
        'unpriced' => UnpricedOrderShippingException::class,
        default => InvalidOrderShippingMethodException::class,
    };

    expect(fn () => UpdateOrderShippingMethod::run($order->fresh(), $identifier))
        ->toThrow($exception);

    $order->refresh();

    expect($order->total->value)->toBe($beforeTotal)
        ->and($order->shippingLines()->count())->toBe(match ($case) {
            'no shipping line' => 0,
            'two shipping lines' => 2,
            default => 1,
        })
        ->and($cart->fresh()->lines()->count())->toBe(0)
        ->and($variant->fresh()->stock)->toBe($stock);

    if (! in_array($case, ['no shipping line', 'two shipping lines'], true)) {
        expect($order->shippingLines()->first()->identifier)->toBe($beforeIdentifier);
    }
})->with([
    'unoffered',
    'unpriced',
    'captured',
    'unauthorized',
    'missing cart',
    'no shipping line',
    'two shipping lines',
]);

it('keeps a waiting order in consultation', function () {
    ['order' => $order, 'line' => $line] = shippingMethodOrder(
        orderMeta: [
            'shippingType' => 'pickup',
            'payment_option' => 'cash-on-delivery',
        ],
        withBilling: true,
    );

    $order->update([
        'status' => 'awaiting-phone-consultation',
    ]);
    $line->update([
        'meta' => ['preorder' => true],
    ]);

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500],
    ]);

    $saved = UpdateOrderShippingMethod::run($order->fresh(), 'COURIER');

    expect($saved->status)->toBe('awaiting-phone-consultation')
        ->and($saved->meta['payment_option'])->toBe('cash-on-delivery')
        ->and($saved->awaitsPreorderConsultation())->toBeTrue()
        ->and($saved->shippingLines()->first()->identifier)->toBe('COURIER');
});

it('leaves the shipping address when personal pickup becomes a courier', function () {
    ['order' => $order, 'cart' => $cart] = shippingMethodOrder(orderMeta: ['shippingType' => 'pickup']);
    $country = Country::factory()->create();

    $billing = OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'billing',
        'country_id' => $country->id,
        'line_one' => 'Invoice Street',
        'postcode' => 'BILL1',
        'city' => 'London',
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'country_id' => $country->id,
        'line_one' => 'Customer Street',
        'postcode' => 'BILL1',
        'city' => 'London',
        'shipping_option' => 'PICKUP',
    ]);

    $cart->shippingAddress->update([
        'postcode' => 'BILL1',
        'line_one' => 'Customer Street',
    ]);

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 900, 'option' => 'National'],
    ], pricesByPostcode: [
        'STORE' => 900,
        'BILL1' => 1500,
    ], requireKnownPostcode: true);

    $action = UpdateOrderShippingMethod::make();
    $saved = $action->execute($order->fresh(), 'COURIER');
    $shipping = $saved->shippingAddress()->first();
    $billing->refresh();

    expect($action->changedPickupToAddressDelivery)->toBeTrue()
        ->and($shipping->line_one)->toBe('Customer Street')
        ->and($shipping->city)->toBe('London')
        ->and($shipping->postcode)->toBe('BILL1')
        ->and($shipping->shipping_option)->toBe('COURIER')
        ->and($billing->line_one)->toBe('Invoice Street')
        ->and($saved->meta['shippingType'])->toBe('courier')
        ->and($saved->shippingLines()->first()->sub_total->value)->toBe(1500)
        ->and($cart->fresh()->shippingAddress->shipping_option)->toBe('COURIER')
        ->and($cart->fresh()->shippingAddress->postcode)->toBe('BILL1');
});

it('treats store collection as personal pickup after the cart address is aligned', function () {
    ['order' => $order] = shippingMethodOrder(identifier: 'PICKUP');

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'country_id' => test()->country->id,
        'line_one' => 'Customer Street',
        'postcode' => 'BILL1',
        'city' => 'London',
        'shipping_option' => 'PICKUP',
    ]);

    offerShippingMethods([
        [
            'identifier' => 'PICKUP',
            'name' => 'Personal pickup',
            'price' => 0,
            'collect' => true,
            'when_postcode' => 'BILL1',
        ],
        [
            'identifier' => 'COURIER',
            'name' => 'Courier',
            'price' => 1500,
            'when_postcode' => 'BILL1',
        ],
    ]);

    $action = UpdateOrderShippingMethod::make();
    $saved = $action->execute($order->fresh(), 'COURIER');

    expect($action->changedPickupToAddressDelivery)->toBeTrue()
        ->and($saved->meta['shippingType'])->toBe('courier')
        ->and($saved->shippingLines()->first()->identifier)->toBe('COURIER');
});

it('keeps the shipping address when personal pickup has no billing address', function () {
    ['order' => $order, 'cart' => $cart] = shippingMethodOrder(orderMeta: ['shippingType' => 'pickup']);

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500],
    ]);

    $action = UpdateOrderShippingMethod::make();
    $saved = $action->execute($order->fresh(), 'COURIER');

    expect($action->changedPickupToAddressDelivery)->toBeTrue()
        ->and($saved->meta['shippingType'])->toBe('courier')
        ->and($saved->shippingLines()->first()->identifier)->toBe('COURIER')
        ->and($saved->billingAddress)->toBeNull()
        ->and($cart->fresh()->shippingAddress->line_one)->toBe('Store Counter');
});

it('does not warn when personal pickup changes to another collection option', function () {
    ['order' => $order] = shippingMethodOrder(orderMeta: ['shippingType' => 'pickup']);

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COLLECT', 'name' => 'Other store', 'price' => 0, 'collect' => true],
    ]);

    $action = UpdateOrderShippingMethod::make();
    $saved = $action->execute($order->fresh(), 'COLLECT');

    expect($action->changedPickupToAddressDelivery)->toBeFalse()
        ->and($saved->meta['shippingType'])->toBe('pickup')
        ->and($saved->shippingLines()->first()->identifier)->toBe('COLLECT');
});

it('keeps the postal address and shipping type when the order is not personal pickup', function () {
    ['order' => $order, 'cart' => $cart] = shippingMethodOrder(
        identifier: 'COURIER',
        name: 'Courier',
        shippingMinor: 900,
        orderMeta: ['shippingType' => 'courier'],
    );

    $country = Country::factory()->create();

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'country_id' => $country->id,
        'line_one' => 'Ship Street',
        'postcode' => 'SHIP1',
        'city' => 'Town',
        'shipping_option' => 'COURIER',
    ]);

    offerShippingMethods([
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 900, 'option' => 'Local'],
        ['identifier' => 'EXPRESS', 'name' => 'Express', 'price' => 1500, 'option' => 'National'],
    ]);

    $action = UpdateOrderShippingMethod::make();
    $saved = $action->execute($order->fresh(), 'EXPRESS');
    $shipping = $saved->shippingAddress()->first();

    expect($action->changedPickupToAddressDelivery)->toBeFalse()
        ->and($shipping->line_one)->toBe('Ship Street')
        ->and($shipping->postcode)->toBe('SHIP1')
        ->and($shipping->shipping_option)->toBe('EXPRESS')
        ->and($saved->meta['shippingType'])->toBe('courier')
        ->and($cart->fresh()->meta['shippingType'])->toBe('courier')
        ->and($cart->fresh()->shippingAddress->shipping_option)->toBe('EXPRESS');
});

it('sets courier shipping type when a locker order changes to a courier', function () {
    ['order' => $order, 'cart' => $cart] = shippingMethodOrder(
        identifier: 'sameday-locker',
        name: 'Easybox',
        shippingMinor: 700,
        orderMeta: ['shippingType' => 'locker'],
    );

    $cart->update([
        'meta' => ['shippingType' => 'locker'],
    ]);

    offerShippingMethods([
        ['identifier' => 'sameday-locker', 'name' => 'Easybox', 'price' => 700],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500],
    ]);

    $action = UpdateOrderShippingMethod::make();
    $saved = $action->execute($order->fresh(), 'COURIER');

    expect($action->changedPickupToAddressDelivery)->toBeFalse()
        ->and($saved->meta['shippingType'])->toBe('courier')
        ->and($cart->fresh()->meta['shippingType'])->toBe('courier')
        ->and($saved->shippingLines()->first()->identifier)->toBe('COURIER');
});

it('sets pickup shipping type when a courier changes to store collection', function () {
    ['order' => $order, 'cart' => $cart] = shippingMethodOrder(
        identifier: 'COURIER',
        name: 'Courier',
        shippingMinor: 1500,
        orderMeta: ['shippingType' => 'courier'],
    );

    $cart->update([
        'meta' => ['shippingType' => 'courier'],
    ]);

    offerShippingMethods([
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500],
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
    ]);

    $action = UpdateOrderShippingMethod::make();
    $saved = $action->execute($order->fresh(), 'PICKUP');

    expect($action->changedPickupToAddressDelivery)->toBeFalse()
        ->and($saved->meta['shippingType'])->toBe('pickup')
        ->and($cart->fresh()->meta['shippingType'])->toBe('pickup')
        ->and($saved->shippingLines()->first()->identifier)->toBe('PICKUP');
});

it('lists the stored shipping address options for personal pickup and rolls the read back', function () {
    ['order' => $order, 'cart' => $cart, 'variant' => $variant] = shippingMethodOrder(orderMeta: ['shippingType' => 'pickup']);
    $country = Country::factory()->create();

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'billing',
        'country_id' => $country->id,
        'line_one' => '10 Main Street',
        'postcode' => 'BILL1',
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'country_id' => $country->id,
        'line_one' => '10 Main Street',
        'postcode' => 'BILL1',
    ]);

    $marker = $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 7,
    ]);

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 900],
        ['identifier' => 'sameday-locker', 'name' => 'Easybox', 'price' => 700],
        ['identifier' => 'SAME', 'name' => 'Courier', 'price' => 1100, 'option' => 'South'],
    ], pricesByPostcode: [
        'BILL1' => 1500,
    ], requireKnownPostcode: true);

    OrderShippingMethodModifier::$options[] = [
        'identifier' => 'NORTH',
        'name' => 'Courier',
        'price' => 1500,
        'collect' => false,
        'option' => 'North',
    ];

    $readable = $order->fresh();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $options = UpdateOrderShippingMethod::make()->offeredOptions($readable);
    $afterFirstRead = count(DB::getQueryLog());
    $again = UpdateOrderShippingMethod::make()->offeredOptions($readable);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    $identifiers = $options->map(fn ($option) => $option->getIdentifier())->all();

    expect($queries)->toBe($afterFirstRead)
        ->and($again->map(fn ($option) => $option->getIdentifier())->all())->toBe($identifiers)
        ->and($identifiers)->toContain('COURIER')
        ->and($identifiers)->toContain('NORTH')
        ->and($identifiers)->not->toContain('sameday-locker')
        ->and($identifiers)->not->toContain('HIDDEN')
        ->and($cart->fresh()->lines()->whereKey($marker->id)->value('quantity'))->toBe(7)
        ->and($order->fresh()->shippingAddress->line_one)->toBe('10 Main Street')
        ->and($order->fresh()->shippingAddress->postcode)->toBe('BILL1')
        ->and($order->fresh()->meta['shippingType'])->toBe('pickup');
});

it('rejects a locker option and leaves the order unchanged', function () {
    ['order' => $order] = shippingMethodOrder(
        identifier: 'COURIER',
        name: 'Courier',
        shippingMinor: 900,
        orderMeta: ['shippingType' => 'courier'],
    );

    offerShippingMethods([
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 900],
        ['identifier' => 'sameday-locker', 'name' => 'Easybox', 'price' => 700],
    ]);

    expect(fn () => UpdateOrderShippingMethod::run($order->fresh(), 'sameday-locker'))
        ->toThrow(InvalidOrderShippingMethodException::class);

    $order->refresh();

    expect($order->shippingLines()->first()->identifier)->toBe('COURIER')
        ->and($order->meta['shippingType'])->toBe('courier')
        ->and($order->shippingLines()->first()->sub_total->value)->toBe(900);
});

it('does not change variant stock', function () {
    ['order' => $order, 'variant' => $variant] = shippingMethodOrder(
        orderMeta: ['shippingType' => 'pickup'],
        withBilling: true,
    );

    offerShippingMethods([
        ['identifier' => 'PICKUP', 'name' => 'Personal pickup', 'price' => 0, 'collect' => true],
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500],
    ]);

    UpdateOrderShippingMethod::run($order, 'COURIER');

    expect($variant->fresh()->stock)->toBe(8);
});
