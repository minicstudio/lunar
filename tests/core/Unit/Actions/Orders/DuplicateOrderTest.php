<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lunar\Actions\Orders\DuplicateOrder;
use Lunar\Base\ShippingModifiers;
use Lunar\DataTypes\Price as PriceDataType;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\ERP\Events\SplitOfflineOrderReadyForErp;
use Lunar\Exceptions\Orders\DuplicateOrderException;
use Lunar\Facades\CartSession;
use Lunar\Facades\ShippingManifest;
use Lunar\Jobs\Orders\MarkAsNewCustomer;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\Tag;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Lunar\Models\TaxZoneCountry;
use Lunar\Models\Transaction;
use Lunar\Tests\Core\Stubs\OrderShippingMethodModifier;
use Lunar\Tests\Core\Stubs\User;
use Lunar\Tests\Core\TestCase;
use Spatie\Activitylog\Models\Activity;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('lunar.orders.payment_options', [
        'bt-ipay' => 'awaiting-payment',
        'stripe-card' => 'awaiting-payment',
        'bank-transfer' => 'awaiting-bank-transfer',
        'cash-on-delivery' => 'payment-offline',
    ]);

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

    $this->customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $this->actingAs(User::factory()->create());
    Gate::define('sales:manage-orders', fn () => true);

    $taxClass = TaxClass::factory()->create([
        'name' => 'Shipping',
        'default' => true,
    ]);
    $taxZone = TaxZone::factory()->create([
        'default' => true,
        'active' => true,
        'zone_type' => 'country',
    ]);
    $taxRate = TaxRate::factory()->create([
        'tax_zone_id' => $taxZone->id,
    ]);
    TaxRateAmount::factory()->create([
        'percentage' => 0,
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);

    $this->country = Country::factory()->create();
    TaxZoneCountry::factory()->create([
        'tax_zone_id' => $taxZone->id,
        'country_id' => $this->country->id,
    ]);

    app(ShippingModifiers::class)->remove(OrderShippingMethodModifier::class);
    app(ShippingModifiers::class)->add(OrderShippingMethodModifier::class);
    OrderShippingMethodModifier::reset();
    ShippingManifest::clearOptions();
});

afterEach(function () {
    app(ShippingModifiers::class)->remove(OrderShippingMethodModifier::class);
    OrderShippingMethodModifier::reset();
    ShippingManifest::clearOptions();
});

/**
 * @param  list<array{identifier: string, name: string, price: int, collect?: bool, option?: ?string}>  $options
 */
function offerDuplicateShipping(array $options): void
{
    OrderShippingMethodModifier::$options = array_map(fn (array $option): array => [
        'identifier' => $option['identifier'],
        'name' => $option['name'],
        'price' => $option['price'],
        'collect' => $option['collect'] ?? false,
        'option' => $option['option'] ?? null,
        'when_postcode' => $option['when_postcode'] ?? null,
    ], $options);
}

/**
 * @param  array<string, mixed>  $lineMeta
 * @param  array<string, mixed>  $orderMeta
 * @return array{order: Order, cart: Cart, variant: ProductVariant, line: OrderLine, customer: Customer, user: User}
 */
function duplicateSourceOrder(
    int $catalogPrice = 2500,
    int $storedPrice = 1000,
    int $quantity = 2,
    int $stock = 8,
    array $lineMeta = [],
    array $orderMeta = [],
    array $variantAttributes = [],
    bool $shippable = true,
    ?string $shippingIdentifier = 'COURIER',
): array {
    $variant = ProductVariant::factory()->create(array_merge([
        'stock' => $stock,
        'purchasable' => 'in_stock',
        'shippable' => $shippable,
    ], $variantAttributes));

    Price::factory()->create([
        'price' => $catalogPrice,
        'min_quantity' => 1,
        'currency_id' => test()->currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $customer = Customer::factory()->create();
    $user = User::factory()->create();
    $cart = Cart::factory()->create([
        'currency_id' => test()->currency->id,
        'channel_id' => test()->channel->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
    ]);
    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => 9,
    ]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'channel_id' => test()->channel->id,
        'currency_code' => test()->currency->code,
        'cart_id' => $cart->id,
        'status' => 'payment-received',
        'reference' => 'SOURCE-REF',
        'customer_reference' => 'PO-44',
        'placed_at' => now()->subDay(),
        'notes' => 'Handle with care',
        'meta' => array_merge([
            'language_locale' => 'ro',
            'is_guest' => false,
            'isBillingSameAsShipping' => true,
            'payment_option' => 'cash-on-delivery',
            'payment_type' => 'cash',
            'shippingType' => 'courier',
            'shipping_zone' => 'old-zone',
            'awb' => 'AWB-1',
            'erp_sent_at' => '2026-01-01T00:00:00Z',
            'billing_series' => 'INV',
            'billing_number' => '100',
            'lunar_invoice_generated_at' => '2026-01-02T00:00:00Z',
            'invoice_emailed_at' => '2026-01-03T00:00:00Z',
            'loyalty_points_to_redeem' => 400,
            'loyalty_discount_minor' => 400,
            'traffic_source' => ['visit_id' => 'visit-1', 'source' => 'ads'],
        ], $orderMeta),
    ]);

    $line = OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => $shippable ? 'physical' : 'digital',
        'quantity' => $quantity,
        'unit_price' => $storedPrice,
        'unit_quantity' => 1,
        'sub_total' => $storedPrice * $quantity,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => $storedPrice * $quantity,
        'notes' => 'Engrave the name',
        'meta' => array_merge([
            'personalization' => 'Alex',
            'preorder' => true,
            'preorder_consultation_completed_at' => '2026-01-04T00:00:00Z',
        ], $lineMeta),
    ]);

    if ($shippingIdentifier !== null) {
        OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => null,
            'purchasable_id' => null,
            'type' => 'shipping',
            'description' => 'Courier',
            'identifier' => $shippingIdentifier,
            'unit_price' => 900,
            'unit_quantity' => 1,
            'quantity' => 1,
            'sub_total' => 900,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 900,
        ]);
    }

    foreach (['billing', 'shipping'] as $type) {
        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'type' => $type,
            'country_id' => test()->country->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'line_one' => '10 Main Street',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'shipping_option' => $type === 'shipping' ? $shippingIdentifier : null,
        ]);
    }

    $order->transactions()->save(Transaction::factory()->make([
        'order_id' => $order->id,
        'type' => 'capture',
        'success' => true,
    ]));

    $tag = Tag::factory()->create(['value' => 'vip-'.$order->id]);
    $order->tags()->attach($tag);

    return compact('order', 'cart', 'variant', 'line', 'customer', 'user');
}

function duplicateCourierOptions(): void
{
    offerDuplicateShipping([
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500, 'option' => 'National'],
        ['identifier' => 'PICKUP', 'name' => 'Store collection', 'price' => 0, 'collect' => true, 'option' => 'Shop'],
    ]);
}

it('previews each shipping method and does not write an order', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $orders = Order::count();
    $carts = Cart::count();

    $preview = DuplicateOrder::make()->preview($built['order']);

    $amounts = collect($preview->shippingMethods)->mapWithKeys(
        fn ($method): array => [$method->identifier => $method->amount]
    );

    expect($preview->paymentMethod)->toBe('cash-on-delivery')
        ->and($preview->shippingMethod)->toBe('COURIER')
        ->and($amounts->get('COURIER'))->toBe(1500)
        ->and($amounts->get('PICKUP'))->toBe(0)
        ->and($amounts)->not->toHaveKey('sameday-locker')
        ->and(Order::count())->toBe($orders)
        ->and(Cart::count())->toBe($carts);
});

it('places one new order from a paid order and takes the copied stock', function () {
    Event::fake([OrderPlacedEvent::class, SplitOfflineOrderReadyForErp::class]);
    Bus::fake([MarkAsNewCustomer::class]);
    activity()->enableLogging();

    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $sessionCart = Cart::factory()->create([
        'currency_id' => test()->currency->id,
        'channel_id' => test()->channel->id,
    ]);
    CartSession::use($sessionCart);

    $source = $built['order']->fresh();
    $stock = $built['variant']->fresh()->stock;
    $sourceUpdated = $source->updated_at?->toJSON();
    $preview = DuplicateOrder::make()->preview($source);
    $courier = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER');

    $created = DuplicateOrder::run($source, 'cash-on-delivery', 'COURIER', $courier->amount);
    $created->load('lines', 'addresses', 'tags', 'transactions');
    $source->refresh();
    $productLine = $created->productLines->first();
    $shippingLine = $created->shippingLines->first();

    expect(Order::query()->whereKeyNot($source->id)->count())->toBe(1)
        ->and($created->reference)->not->toBe($source->reference)
        ->and($created->placed_at?->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'))
        ->and($created->status)->toBe('payment-offline')
        ->and($created->meta['payment_option'])->toBe('cash-on-delivery')
        ->and($created->meta['payment_type'])->toBe('cash')
        ->and($created->meta['shippingType'])->toBe('courier')
        ->and($created->meta['language_locale'])->toBe('ro')
        ->and($created->meta['is_guest'])->toBeFalse()
        ->and($created->meta['isBillingSameAsShipping'])->toBeTrue()
        ->and((int) $created->meta['duplicated_from_order_id'])->toBe($source->id)
        ->and($shippingLine->identifier)->toBe('COURIER')
        ->and($created->shipping_total->value)->toBe($courier->amount)
        ->and($productLine->unit_price->value)->toBe(2500)
        ->and($productLine->quantity)->toBe(2)
        ->and($productLine->notes)->toBe('Engrave the name')
        ->and($productLine->meta['personalization'])->toBe('Alex')
        ->and($created->notes)->toBe('Handle with care')
        ->and($created->customer_reference)->toBe('PO-44')
        ->and($created->tags->pluck('value')->all())->toContain('VIP-'.$source->id)
        ->and($created->customer_id)->toBe($built['customer']->id)
        ->and($created->user_id)->toBe($built['user']->id)
        ->and($created->channel_id)->toBe(test()->channel->id)
        ->and($created->currency_code)->toBe(test()->currency->code)
        ->and($created->shippingAddress->line_one)->toBe('10 Main Street')
        ->and($created->billingAddress->first_name)->toBe('Ada')
        ->and($created->cart_id)->not->toBe($built['cart']->id)
        ->and($created->cart_id)->not->toBe($sessionCart->id)
        ->and($created->transactions)->toHaveCount(0)
        ->and(Activity::query()->forSubject($created)->where('description', 'status-update')->count())->toBe(0)
        ->and($source->status)->toBe('payment-received')
        ->and($source->updated_at?->toJSON())->toBe($sourceUpdated)
        ->and($source->transactions()->count())->toBe(1)
        ->and($built['variant']->fresh()->stock)->toBe($stock - 2)
        ->and($created->meta['stock_taken'])->toBeTrue()
        ->and($built['cart']->fresh()->lines()->count())->toBe(1);

    Event::assertNotDispatched(OrderPlacedEvent::class);
    Event::assertNotDispatched(SplitOfflineOrderReadyForErp::class);
    Bus::assertDispatchedTimes(MarkAsNewCustomer::class, 1);
});

it('stores the status configured for the payment method', function (string $payment, string $status) {
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $preview = DuplicateOrder::make()->preview($built['order']);
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;
    $createdAs = null;

    Order::created(function (Order $order) use (&$createdAs): void {
        $createdAs = $order->status;
    });

    $created = DuplicateOrder::run($built['order'], $payment, 'COURIER', $amount);

    expect($created->status)->toBe($status)
        ->and($createdAs)->toBe($status)
        ->and($created->meta['payment_option'])->toBe($payment);
})->with([
    'card' => ['stripe-card', 'awaiting-payment'],
    'bank transfer' => ['bank-transfer', 'awaiting-bank-transfer'],
]);

it('prices the other shipping method from the preview', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $preview = DuplicateOrder::make()->preview($built['order']);
    $pickup = collect($preview->shippingMethods)->firstWhere('identifier', 'PICKUP');

    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', 'PICKUP', $pickup->amount);

    expect($pickup->amount)->toBe(0)
        ->and($created->shipping_total->value)->toBe(0)
        ->and($created->shippingLines->first()->identifier)->toBe('PICKUP')
        ->and($created->meta['shippingType'])->toBe('pickup')
        ->and($created->meta['shipping_price_override'] ?? null)->toBeNull()
        ->and($created->shippingLines->first()->meta['shipping_price_override'] ?? null)->toBeNull();
});

it('stores a typed shipping price and keeps it when shipping would be free', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => null,
            'free_shipping' => true,
        ],
    ]);
    attachDuplicateDiscount($discount);

    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', 'COURIER', 5);
    $line = $created->shippingLines()->first();

    expect($created->shipping_total->value)->toBe(5)
        ->and($line->total->value)->toBe(5)
        ->and($created->meta['shipping_price_override'])->toBeTrue()
        ->and($line->meta['shipping_price_override'])->toBeTrue();
});

it('does not override a calculated price and stores free shipping as zero', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => null,
            'free_shipping' => true,
        ],
    ]);
    attachDuplicateDiscount($discount);

    $preview = DuplicateOrder::make()->preview($built['order']);
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;
    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', 'COURIER', $amount);

    expect($amount)->toBe(0)
        ->and($created->shipping_total->value)->toBe(0)
        ->and($created->meta['shipping_price_override'] ?? null)->toBeNull()
        ->and($created->shippingLines()->first()->meta['shipping_price_override'] ?? null)->toBeNull();
});

it('recalculates an automatic discount and drops one the lines no longer qualify for', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder(catalogPrice: 2000, storedPrice: 5000);
    $qualifying = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);
    attachDuplicateDiscount($qualifying);
    $qualifying->discountables()->create([
        'discountable_type' => $built['variant']->getMorphClass(),
        'discountable_id' => $built['variant']->id,
        'type' => 'limitation',
    ]);

    $other = ProductVariant::factory()->create();
    $omitted = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 25,
        ],
    ]);
    attachDuplicateDiscount($omitted);
    $omitted->discountables()->create([
        'discountable_type' => $other->getMorphClass(),
        'discountable_id' => $other->id,
        'type' => 'limitation',
    ]);
    $built['order']->update([
        'discount_breakdown' => discountBreakdown([$qualifying->id, $omitted->id]),
    ]);

    $preview = DuplicateOrder::make()->preview($built['order']->fresh());
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;
    $created = DuplicateOrder::run($built['order']->fresh(), 'cash-on-delivery', 'COURIER', $amount);
    $ids = collect($created->discount_breakdown)->pluck('discount_id');

    expect($created->discount_total->value)->toBe(400)
        ->and($ids)->toContain($qualifying->id)
        ->and($ids)->not->toContain($omitted->id);
});

it('applies a coupon that still qualifies without using it up', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder(catalogPrice: 2000, storedPrice: 2000, quantity: 1);
    $coupon = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'SAVE10',
        'uses' => 2,
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);
    attachDuplicateDiscount($coupon);
    $built['order']->update([
        'discount_breakdown' => discountBreakdown([$coupon->id]),
    ]);

    $preview = DuplicateOrder::make()->preview($built['order']->fresh());
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;
    $created = DuplicateOrder::run($built['order']->fresh(), 'cash-on-delivery', 'COURIER', $amount);

    expect(collect($created->discount_breakdown)->pluck('discount_id'))->toContain($coupon->id)
        ->and($created->discount_total->value)->toBe(200)
        ->and($coupon->fresh()->uses)->toBe(2);
});

it('omits a coupon that current rules reject and still places the order', function (string $case) {
    duplicateCourierOptions();
    $built = duplicateSourceOrder(catalogPrice: 1000, storedPrice: 1000, quantity: 1);
    $attributes = [
        'type' => AdvancedAmountOff::class,
        'coupon' => 'OLD',
        'uses' => 0,
        'starts_at' => now()->subDay(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ];

    if ($case === 'expired') {
        $attributes['ends_at'] = now()->subMinute();
    }

    if ($case === 'exhausted') {
        $attributes['uses'] = 1;
        $attributes['max_uses'] = 1;
    }

    if ($case === 'minimum') {
        $attributes['data']['min_prices'] = [
            test()->currency->code => 500000,
        ];
    }

    $coupon = Discount::factory()->create($attributes);
    attachDuplicateDiscount($coupon);

    if ($case === 'ineligible') {
        $other = ProductVariant::factory()->create();
        $coupon->discountables()->create([
            'discountable_type' => $other->getMorphClass(),
            'discountable_id' => $other->id,
            'type' => 'limitation',
        ]);
    }

    $built['order']->update([
        'discount_breakdown' => discountBreakdown([$coupon->id]),
    ]);

    $orders = Order::count();
    $preview = DuplicateOrder::make()->preview($built['order']->fresh());
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;
    $created = DuplicateOrder::run($built['order']->fresh(), 'cash-on-delivery', 'COURIER', $amount);

    expect(Order::count())->toBe($orders + 1)
        ->and(collect($created->discount_breakdown ?? [])->pluck('discount_id'))->not->toContain($coupon->id);
})->with(['expired', 'exhausted', 'minimum', 'ineligible']);

it('does not copy a loyalty redemption', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $preview = DuplicateOrder::make()->preview($built['order']);
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;

    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', 'COURIER', $amount);
    $source = $built['order']->fresh();

    expect($created->meta['loyalty_points_to_redeem'] ?? null)->toBeNull()
        ->and($created->meta['loyalty_discount_minor'] ?? null)->toBeNull()
        ->and((int) $source->meta['loyalty_points_to_redeem'])->toBe(400)
        ->and((int) $source->meta['loyalty_discount_minor'])->toBe(400);
});

it('does not add a shipping line when the order does not ship', function () {
    offerDuplicateShipping([
        ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500],
    ]);
    $built = duplicateSourceOrder(shippable: false, shippingIdentifier: null);

    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', null, null);

    expect($created->shippingLines()->count())->toBe(0);
});

it('stores a different payment method without the source payment type', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $preview = DuplicateOrder::make()->preview($built['order']);
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;

    $created = DuplicateOrder::run($built['order'], 'bank-transfer', 'COURIER', $amount);

    expect($created->meta['payment_option'])->toBe('bank-transfer')
        ->and($created->meta['payment_type'] ?? null)->toBeNull()
        ->and($created->status)->toBe('awaiting-bank-transfer');
});

it('rejects a duplicate and keeps the source, the carts, and the stock', function (string $reason, callable $prepare) {
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $prepared = $prepare($built);
    $orders = Order::count();
    $carts = Cart::count();
    $stock = $built['variant']->fresh()->stock;
    $updated = $built['order']->fresh()->updated_at?->toJSON();

    expect(fn () => DuplicateOrder::run(
        $built['order']->fresh(),
        $prepared['payment'],
        $prepared['shipping'],
        $prepared['amount'],
    ))->toThrow(function (DuplicateOrderException $exception) use ($reason) {
        expect($exception->reason)->toBe($reason);
    });

    expect(Order::count())->toBe($orders)
        ->and(Cart::count())->toBe($carts)
        ->and($built['variant']->fresh()->stock)->toBe($stock)
        ->and($built['order']->fresh()->status)->toBe('payment-received')
        ->and($built['order']->fresh()->updated_at?->toJSON())->toBe($updated);
})->with([
    'unauthorized' => ['unauthorized', function (): array {
        Gate::define('sales:manage-orders', fn () => false);

        return ['payment' => 'cash-on-delivery', 'shipping' => 'COURIER', 'amount' => 1500];
    }],
    'no products' => ['no_products', function (array $built): array {
        $built['order']->productLines()->delete();

        return ['payment' => 'cash-on-delivery', 'shipping' => 'COURIER', 'amount' => 1500];
    }],
    'missing product' => ['unavailable', function (array $built): array {
        $built['variant']->delete();

        return ['payment' => 'cash-on-delivery', 'shipping' => 'COURIER', 'amount' => 1500];
    }],
    'unpurchasable product' => ['unavailable', function (array $built): array {
        $built['variant']->product->update(['status' => 'draft']);

        return ['payment' => 'cash-on-delivery', 'shipping' => 'COURIER', 'amount' => 1500];
    }],
    'availability' => ['unavailable', function (array $built): array {
        $built['variant']->product->customerGroups()->updateExistingPivot(test()->customerGroup->id, [
            'purchasable' => false,
        ]);

        return ['payment' => 'cash-on-delivery', 'shipping' => 'COURIER', 'amount' => 1500];
    }],
    'quantity' => ['quantity', function (array $built): array {
        $built['variant']->update(['min_quantity' => 5]);

        return ['payment' => 'cash-on-delivery', 'shipping' => 'COURIER', 'amount' => 1500];
    }],
    'stock' => ['stock', function (array $built): array {
        $built['variant']->update(['stock' => 1]);

        return ['payment' => 'cash-on-delivery', 'shipping' => 'COURIER', 'amount' => 1500];
    }],
    'address' => ['address', function (array $built): array {
        $built['order']->billingAddress->update(['postcode' => null]);

        return ['payment' => 'cash-on-delivery', 'shipping' => 'COURIER', 'amount' => 1500];
    }],
    'payment' => ['payment', function (): array {
        return ['payment' => 'store-credit', 'shipping' => 'COURIER', 'amount' => 1500];
    }],
    'shipping' => ['shipping', function (): array {
        return ['payment' => 'cash-on-delivery', 'shipping' => 'UNKNOWN', 'amount' => 1500];
    }],
    'locker' => ['shipping', function (): array {
        offerDuplicateShipping([
            ['identifier' => 'COURIER', 'name' => 'Courier', 'price' => 1500],
            ['identifier' => 'sameday-locker', 'name' => 'Locker', 'price' => 700],
        ]);

        return ['payment' => 'cash-on-delivery', 'shipping' => 'sameday-locker', 'amount' => 700];
    }],
    'negative shipping' => ['negative_shipping', function (): array {
        return ['payment' => 'cash-on-delivery', 'shipping' => 'COURIER', 'amount' => -1];
    }],
]);

it('does not reject a preorder or an always-purchasable variant that has no stock', function (string $purchasable) {
    duplicateCourierOptions();
    $built = duplicateSourceOrder(stock: 0, variantAttributes: [
        'purchasable' => $purchasable,
    ]);
    $preview = DuplicateOrder::make()->preview($built['order']);
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;

    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', 'COURIER', $amount);

    expect($created->id)->not->toBe($built['order']->id)
        ->and($built['variant']->fresh()->stock)->toBe(0);
})->with(['preorder', 'always']);

it('leaves invoice, shipping, loyalty, and traffic records behind', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $preview = DuplicateOrder::make()->preview($built['order']);
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;

    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', 'COURIER', $amount);

    expect($created->meta['awb'] ?? null)->toBeNull()
        ->and($created->meta['erp_sent_at'] ?? null)->toBeNull()
        ->and($created->meta['billing_series'] ?? null)->toBeNull()
        ->and($created->meta['billing_number'] ?? null)->toBeNull()
        ->and($created->meta['lunar_invoice_generated_at'] ?? null)->toBeNull()
        ->and($created->meta['invoice_emailed_at'] ?? null)->toBeNull()
        ->and($created->meta['traffic_source'] ?? null)->toBeNull()
        ->and($created->meta['shipping_zone'] ?? null)->toBeNull()
        ->and($created->meta['loyalty_points_to_redeem'] ?? null)->toBeNull();
});

it('waits for consultation when the copied line is still a preorder', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder(variantAttributes: [
        'purchasable' => 'preorder',
    ]);
    $preview = DuplicateOrder::make()->preview($built['order']);
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;

    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', 'COURIER', $amount);
    $line = $created->productLines()->first();

    expect($line->meta['preorder'] ?? null)->toBeTrue()
        ->and($line->meta['preorder_consultation_completed_at'] ?? null)->toBeNull()
        ->and($created->awaitsPreorderConsultation())->toBeTrue();
});

it('does not record a preorder once the variant is no longer a preorder', function () {
    duplicateCourierOptions();
    $built = duplicateSourceOrder(variantAttributes: [
        'purchasable' => 'in_stock',
    ]);
    $preview = DuplicateOrder::make()->preview($built['order']);
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;

    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', 'COURIER', $amount);
    $line = $created->productLines()->first();

    expect($line->meta['preorder'] ?? null)->not->toBeTrue()
        ->and($line->meta['preorder_consultation_completed_at'] ?? null)->toBeNull();
});

it('does not dispatch placement when cash on delivery is chosen', function () {
    Event::fake([OrderPlacedEvent::class, SplitOfflineOrderReadyForErp::class]);
    duplicateCourierOptions();
    $built = duplicateSourceOrder();
    $preview = DuplicateOrder::make()->preview($built['order']);
    $amount = collect($preview->shippingMethods)->firstWhere('identifier', 'COURIER')->amount;

    $created = DuplicateOrder::run($built['order'], 'cash-on-delivery', 'COURIER', $amount);

    expect($created->status)->toBe('payment-offline');
    Event::assertNotDispatched(OrderPlacedEvent::class);
    Event::assertNotDispatched(SplitOfflineOrderReadyForErp::class);
});

function attachDuplicateDiscount(Discount $discount): void
{
    $discount->channels()->attach([
        test()->channel->id => [
            'enabled' => true,
            'starts_at' => now()->subMinute(),
        ],
    ]);
    $discount->customerGroups()->attach([
        test()->customerGroup->id => [
            'enabled' => true,
            'starts_at' => now()->subMinute(),
        ],
    ]);
}

/**
 * @param  list<int>  $ids
 */
function discountBreakdown(array $ids): \Illuminate\Support\Collection
{
    return collect($ids)->map(fn (int $id): object => (object) [
        'discount_id' => $id,
        'lines' => collect(),
        'total' => new PriceDataType(0, test()->currency, 1),
    ]);
}
