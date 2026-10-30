<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lunar\Actions\Orders\SplitOrder;
use Lunar\Actions\Orders\DTOs\SplitOrderRequest;
use Lunar\Actions\Orders\DTOs\SplitOrderTarget;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdownAmount;
use Lunar\DataTypes\Price as PriceDataType;
use Lunar\DataTypes\ShippingOption;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\Exceptions\Orders\InvalidOrderSplitException;
use Lunar\Exceptions\Orders\LockedOrderSplitException;
use Lunar\Exceptions\Orders\UnauthorizedOrderSplitException;
use Lunar\Facades\ShippingManifest;
use Lunar\Jobs\Orders\MarkAsNewCustomer;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
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
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRateAmount;
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

    $this->customerGroup = CustomerGroup::factory()->create([
        'default' => true,
    ]);

    $this->actingAs(User::factory()->create());
    Gate::define('sales:manage-orders', fn () => true);
});

/**
 * @param  list<array{variant: ProductVariant, quantity: int, price: int, meta?: array<string, mixed>}>  $lines
 */
function splittableOrder(array $lines, array $attributes = [], bool $shipping = false): Order
{
    $customer = Customer::factory()->create();
    $user = User::factory()->create();

    $order = Order::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'channel_id' => test()->channel->id,
        'currency_code' => test()->currency->code,
        'status' => 'awaiting-payment',
        'reference' => 'ORIGINAL-'.str()->uuid(),
        'placed_at' => now()->subDay(),
        'notes' => 'keep-notes',
        'new_customer' => true,
        'meta' => [
            'payment_option' => 'cash-on-delivery',
            'payment_type' => 'cod',
            'language_locale' => 'en',
            'is_guest' => false,
        ],
    ], $attributes));

    foreach ($lines as $line) {
        OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => $line['variant']->getMorphClass(),
            'purchasable_id' => $line['variant']->id,
            'type' => 'physical',
            'quantity' => $line['quantity'],
            'unit_price' => $line['price'],
            'unit_quantity' => 1,
            'sub_total' => $line['price'] * $line['quantity'],
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => $line['price'] * $line['quantity'],
            'meta' => $line['meta'] ?? null,
        ]);
    }

    if ($shipping) {
        OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => null,
            'purchasable_id' => null,
            'type' => 'shipping',
            'identifier' => 'courier',
            'description' => 'Courier',
            'quantity' => 1,
            'unit_price' => 500,
            'unit_quantity' => 1,
            'sub_total' => 500,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 500,
        ]);
    }

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'line_one' => 'Ship Street',
    ]);
    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'billing',
        'line_one' => 'Bill Street',
    ]);

    return $order->refresh();
}

function pricedSplitVariant(int $price, array $attributes = []): ProductVariant
{
    $variant = ProductVariant::factory()->create($attributes);

    Price::factory()->create([
        'price' => $price,
        'min_quantity' => 1,
        'currency_id' => test()->currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    return $variant;
}

/**
 * @param  list<array{key: string, quantities: array<int, int>, shipping?: ?int, payment?: string}>  $targets
 */
/**
 * @param  list<int>  $catalogPricedLineIds
 */
function splitRequest(array $targets, ?string $couponTarget = null, bool $applyCoupon = false, ?string $loyaltyTarget = null, bool $applyLoyalty = false, array $catalogPricedLineIds = []): SplitOrderRequest
{
    return new SplitOrderRequest(
        targets: array_map(fn (array $target): SplitOrderTarget => new SplitOrderTarget(
            key: $target['key'],
            quantities: $target['quantities'],
            shippingMinor: array_key_exists('shipping', $target) ? $target['shipping'] : 0,
            paymentOption: $target['payment'] ?? 'cash-on-delivery',
            paymentType: 'cod',
        ), $targets),
        couponTarget: $couponTarget,
        applyCoupon: $applyCoupon,
        loyaltyTarget: $loyaltyTarget,
        applyLoyalty: $applyLoyalty,
        catalogPricedLineIds: $catalogPricedLineIds,
    );
}

function attachSplitDiscount(Discount $discount): void
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

it('rejects an unauthorized user, a captured order, an awb, an erp timestamp, and a dispatched order', function (string $case) {
    $variant = pricedSplitVariant(1000);
    $other = pricedSplitVariant(1000);
    $order = splittableOrder([
        ['variant' => $variant, 'quantity' => 1, 'price' => 1000],
        ['variant' => $other, 'quantity' => 1, 'price' => 1000],
    ]);
    $lines = $order->productLines()->get();
    $stock = $variant->fresh()->stock;

    match ($case) {
        'unauthorized' => Gate::define('sales:manage-orders', fn () => false),
        'captured' => $order->transactions()->save(Transaction::factory()->make([
            'order_id' => $order->id,
            'type' => 'capture',
            'success' => true,
        ])),
        'awb' => $order->update(['meta' => ['awb' => 'AWB-1', 'payment_option' => 'cash-on-delivery']]),
        'erp' => $order->update(['meta' => ['erp_sent_at' => now()->toIso8601String(), 'payment_option' => 'cash-on-delivery']]),
        'dispatched' => $order->update(['status' => 'dispatched']),
    };

    $beforeOrders = Order::count();

    $request = splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1]],
    ]);

    $exception = $case === 'unauthorized'
        ? UnauthorizedOrderSplitException::class
        : LockedOrderSplitException::class;

    expect(fn () => SplitOrder::run($order->fresh(), $request))->toThrow($exception);
    expect(Order::count())->toBe($beforeOrders)
        ->and($variant->fresh()->stock)->toBe($stock);
})->with([
    'unauthorized',
    'captured',
    'awb',
    'erp',
    'dispatched',
]);

it('stores a three-way quantity split and rejects an unassigned unit or a later failure', function () {
    $variant = pricedSplitVariant(1000, ['stock' => 9]);
    $order = splittableOrder([
        ['variant' => $variant, 'quantity' => 6, 'price' => 1000, 'meta' => ['personalization' => 'Hello']],
    ]);
    $line = $order->productLines()->first();

    $saved = SplitOrder::run($order, splitRequest([
        ['key' => '1', 'quantities' => [$line->id => 1]],
        ['key' => '2', 'quantities' => [$line->id => 2]],
        ['key' => '3', 'quantities' => [$line->id => 3]],
    ]));

    $children = Order::query()->where('id', '!=', $order->id)->get();
    $quantities = $children->flatMap(fn (Order $child) => $child->productLines->pluck('quantity'))->sort()->values()->all();

    expect($saved->status)->toBe('split')
        ->and($quantities)->toBe([1, 2, 3])
        ->and($children->flatMap(fn (Order $child) => $child->productLines)->every(
            fn (OrderLine $childLine): bool => ($childLine->meta['personalization'] ?? null) === 'Hello'
        ))->toBeTrue()
        ->and($order->fresh()->productLines()->first()->quantity)->toBe(6);

    $again = splittableOrder([
        ['variant' => $variant, 'quantity' => 2, 'price' => 1000],
    ]);
    $againLine = $again->productLines()->first();
    $before = Order::count();

    expect(fn () => SplitOrder::run($again, splitRequest([
        ['key' => '1', 'quantities' => [$againLine->id => 1]],
        ['key' => '2', 'quantities' => [$againLine->id => 0]],
    ])))->toThrow(InvalidOrderSplitException::class);

    expect(Order::count())->toBe($before)
        ->and($again->fresh()->status)->toBe('awaiting-payment');

    $failing = splittableOrder([
        ['variant' => $variant, 'quantity' => 2, 'price' => 1000],
    ]);
    $failingLine = $failing->productLines()->first();
    $beforeFail = Order::count();

    Order::created(function (Order $created) use ($failing): void {
        if ($created->id !== $failing->id) {
            throw new RuntimeException('split failed');
        }
    });

    expect(fn () => SplitOrder::run($failing, splitRequest([
        ['key' => '1', 'quantities' => [$failingLine->id => 1]],
        ['key' => '2', 'quantities' => [$failingLine->id => 1]],
    ])))->toThrow(RuntimeException::class);

    expect(Order::count())->toBe($beforeFail)
        ->and($failing->fresh()->status)->toBe('awaiting-payment');
});

it('prices each target on its own cart and stores an edited shipping total', function () {
    $variant = pricedSplitVariant(1000);
    $other = pricedSplitVariant(1000);
    $order = splittableOrder([
        ['variant' => $variant, 'quantity' => 1, 'price' => 1000],
        ['variant' => $other, 'quantity' => 1, 'price' => 1000],
    ], shipping: true);
    $lines = $order->productLines()->orderBy('id')->get();
    $taxClass = $variant->taxClass;
    $cartsBefore = Cart::count();
    $ordersBefore = Order::count();

    $preview = SplitOrder::make()->preview($order, splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1], 'shipping' => null],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1], 'shipping' => null],
    ]));

    expect(Cart::count())->toBe($cartsBefore)
        ->and(Order::count())->toBe($ordersBefore)
        ->and($preview->targets[0]->shippingMinor)->toBe(0)
        ->and($preview->targets[0]->shippingPricingUnavailable)->toBeTrue()
        ->and($preview->targets[0]->lines[0]['unit_price'])->toBe(1000)
        ->and($preview->targets[0]->lines[0]['sub_total'])->toBe(1000)
        ->and($preview->targets[0]->lines[0]['discount'])->toBe(0)
        ->and($preview->targets[0]->lines[0]['quantity'])->toBe(1);

    ShippingManifest::getOptionUsing(function ($cart, string $identifier) use ($taxClass) {
        return new ShippingOption(
            name: 'Courier',
            description: null,
            identifier: $identifier,
            price: new PriceDataType(750, $cart->currency, 1),
            taxClass: $taxClass,
        );
    });

    $pricedPreview = SplitOrder::make()->preview($order, splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1], 'shipping' => null],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1], 'shipping' => null],
    ]));

    expect($pricedPreview->targets[0]->shippingMinor)->toBe(750)
        ->and($pricedPreview->targets[0]->shippingPricingUnavailable)->toBeFalse()
        ->and(Cart::count())->toBe($cartsBefore);

    ShippingManifest::getOptionUsing(fn () => null);

    $saved = SplitOrder::run($order->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1], 'shipping' => 1000],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1], 'shipping' => 0],
    ]));

    $edited = Order::query()->where('id', '!=', $order->id)->get()->first(
        fn (Order $child): bool => (int) $child->shippingLines()->first()?->sub_total->value === 1000
    );

    expect($edited)->not->toBeNull()
        ->and($edited->shipping_total->value)->toBe(1200)
        ->and($edited->shippingLines()->first()->total->value)->toBe(1200)
        ->and($edited->tax_total->value)->toBe(400)
        ->and($edited->shippingLines()->first()->identifier)->toBe('courier')
        ->and($saved->fresh()->status)->toBe('split');
});

it('keeps the original shipping tax class when the courier cannot be priced', function () {
    TaxRateAmount::factory()->create([
        'tax_class_id' => TaxClass::factory()->create(['name' => 'Wrong'])->id,
        'percentage' => 5,
    ]);

    $shippingRate = TaxRateAmount::factory()->create([
        'tax_class_id' => TaxClass::factory()->create(['name' => 'Shipping'])->id,
        'percentage' => 9,
    ]);

    $variant = pricedSplitVariant(1000);
    $other = pricedSplitVariant(1000);
    $order = splittableOrder([
        ['variant' => $variant, 'quantity' => 1, 'price' => 1000],
        ['variant' => $other, 'quantity' => 1, 'price' => 1000],
    ], shipping: true);

    $breakdown = new TaxBreakdown;
    $breakdown->addAmount(new TaxBreakdownAmount(
        price: new PriceDataType(0, test()->currency, 1),
        identifier: 'tax_rate_'.$shippingRate->tax_rate_id,
        description: 'Shipping tax',
        percentage: 9,
    ));
    $order->shippingLines()->first()->update([
        'tax_breakdown' => $breakdown,
    ]);

    $lines = $order->productLines()->orderBy('id')->get();

    ShippingManifest::getOptionUsing(fn (): null => null);

    try {
        SplitOrder::run($order->fresh(), splitRequest([
            ['key' => '1', 'quantities' => [$lines[0]->id => 1], 'shipping' => 1000],
            ['key' => '2', 'quantities' => [$lines[1]->id => 1], 'shipping' => 0],
        ]));
    } finally {
        ShippingManifest::getOptionUsing(fn (): null => null);
    }

    $child = Order::query()->where('id', '!=', $order->id)->get()->first(
        fn (Order $created): bool => (int) $created->shippingLines()->first()?->sub_total->value === 1000
    );

    expect($child)->not->toBeNull()
        ->and($child->shipping_total->value)->toBe(1090)
        ->and($child->shippingLines()->first()->total->value)->toBe(1090)
        ->and((float) $child->shippingLines()->first()->tax_breakdown->amounts->first()->percentage)->toBe(9.0)
        ->and($child->shippingLines()->first()->tax_total->value)->toBe(90);
});

it('applies a coupon to one order, including below the minimum and capped fixed amounts', function () {
    $left = pricedSplitVariant(1000);
    $right = pricedSplitVariant(4000);
    $order = splittableOrder([
        ['variant' => $left, 'quantity' => 1, 'price' => 1000],
        ['variant' => $right, 'quantity' => 1, 'price' => 4000],
    ]);
    $lines = $order->productLines()->orderBy('id')->get();

    $coupon = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'SAVE10',
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
            'min_prices' => [
                test()->currency->code => 50000,
            ],
        ],
    ]);
    attachSplitDiscount($coupon);
    $order->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $coupon->id,
                'lines' => collect(),
                'total' => new PriceDataType(0, test()->currency, 1),
            ],
        ]),
    ]);

    $preview = SplitOrder::make()->preview($order->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1]],
    ], couponTarget: '1', applyCoupon: true));

    expect($preview->targets[0]->lines[0]['unit_price'])->toBe(1000)
        ->and($preview->targets[0]->lines[0]['sub_total'])->toBe(1000)
        ->and($preview->targets[0]->lines[0]['discount'])->toBe(100)
        ->and($preview->targets[1]->lines[0]['discount'])->toBe(0);

    SplitOrder::run($order->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1]],
    ], couponTarget: '1', applyCoupon: true));

    $children = Order::query()->where('id', '!=', $order->id)->with('lines')->get();
    $chosen = $children->first(fn (Order $child) => $child->productLines->contains('purchasable_id', $left->id));
    $other = $children->first(fn (Order $child) => $child->productLines->contains('purchasable_id', $right->id));

    expect($chosen->discount_total->value)->toBe(100)
        ->and($chosen->discount_breakdown->pluck('discount_id')->all())->toContain($coupon->id)
        ->and($other->discount_total->value)->toBe(0);

    $fixedLeft = pricedSplitVariant(1000);
    $fixedRight = pricedSplitVariant(1000);
    $fixedOrder = splittableOrder([
        ['variant' => $fixedLeft, 'quantity' => 1, 'price' => 1000],
        ['variant' => $fixedRight, 'quantity' => 1, 'price' => 1000],
    ]);
    $fixedLines = $fixedOrder->productLines()->orderBy('id')->get();
    $fixed = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'FIXED',
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => true,
            'fixed_values' => [
                test()->currency->code => 5000,
            ],
        ],
    ]);
    attachSplitDiscount($fixed);
    $fixedOrder->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $fixed->id,
                'lines' => collect(),
                'total' => new PriceDataType(0, test()->currency, 1),
            ],
        ]),
    ]);

    SplitOrder::run($fixedOrder->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$fixedLines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$fixedLines[1]->id => 1]],
    ], couponTarget: '1', applyCoupon: true));

    $capped = Order::query()->where('id', '!=', $fixedOrder->id)->get()->first(
        fn (Order $child) => $child->productLines->contains('purchasable_id', $fixedLeft->id)
    );

    expect($capped->discount_total->value)->toBe(1000);

    $skippedLeft = pricedSplitVariant(1000);
    $skippedRight = pricedSplitVariant(1000);
    $skippedOrder = splittableOrder([
        ['variant' => $skippedLeft, 'quantity' => 1, 'price' => 1000],
        ['variant' => $skippedRight, 'quantity' => 1, 'price' => 1000],
    ]);
    $skippedLines = $skippedOrder->productLines()->orderBy('id')->get();
    $skippedOrder->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $coupon->id,
                'lines' => collect(),
                'total' => new PriceDataType(0, test()->currency, 1),
            ],
        ]),
    ]);

    SplitOrder::run($skippedOrder->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$skippedLines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$skippedLines[1]->id => 1]],
    ], applyCoupon: false));

    $skippedChildren = Order::query()
        ->where('id', '!=', $skippedOrder->id)
        ->get()
        ->filter(fn (Order $child): bool => ($child->meta['split_from_order_id'] ?? null) === $skippedOrder->id);

    expect($skippedChildren->every(fn (Order $child): bool => (int) $child->discount_total->value === 0))->toBeTrue();
});

it('keeps an original-order coupon that has expired or no longer matches the assigned products', function () {
    $left = pricedSplitVariant(1000);
    $right = pricedSplitVariant(4000);
    $order = splittableOrder([
        ['variant' => $left, 'quantity' => 1, 'price' => 1000],
        ['variant' => $right, 'quantity' => 1, 'price' => 4000],
    ]);
    $lines = $order->productLines()->orderBy('id')->get();

    $expired = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'EXPIRED10',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->subDay(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);
    attachSplitDiscount($expired);
    $order->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $expired->id,
                'lines' => collect(),
                'total' => new PriceDataType(0, test()->currency, 1),
            ],
        ]),
    ]);

    SplitOrder::run($order->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1]],
    ], couponTarget: '1', applyCoupon: true));

    $expiredChild = Order::query()->where('id', '!=', $order->id)->get()->first(
        fn (Order $child) => $child->productLines->contains('purchasable_id', $left->id)
    );

    expect($expiredChild->discount_total->value)->toBe(100)
        ->and($expiredChild->discount_breakdown->pluck('discount_id')->all())->toContain($expired->id);

    $limitedLeft = pricedSplitVariant(1000);
    $limitedRight = pricedSplitVariant(4000);
    $limitedOrder = splittableOrder([
        ['variant' => $limitedLeft, 'quantity' => 1, 'price' => 1000],
        ['variant' => $limitedRight, 'quantity' => 1, 'price' => 4000],
    ]);
    $limitedLines = $limitedOrder->productLines()->orderBy('id')->get();
    $limited = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'LIMITED10',
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);
    attachSplitDiscount($limited);
    $limited->discountables()->create([
        'discountable_type' => $limitedLeft->getMorphClass(),
        'discountable_id' => $limitedLeft->id,
        'type' => 'limitation',
    ]);
    $limitedOrder->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $limited->id,
                'lines' => collect(),
                'total' => new PriceDataType(0, test()->currency, 1),
            ],
        ]),
    ]);

    SplitOrder::run($limitedOrder->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$limitedLines[1]->id => 1]],
        ['key' => '2', 'quantities' => [$limitedLines[0]->id => 1]],
    ], couponTarget: '1', applyCoupon: true));

    $limitedChild = Order::query()->where('id', '!=', $limitedOrder->id)->get()->first(
        fn (Order $child) => $child->productLines->contains('purchasable_id', $limitedRight->id)
    );

    expect($limitedChild->discount_total->value)->toBe(400)
        ->and($limitedChild->discount_breakdown->pluck('discount_id')->all())->toContain($limited->id);
});

it('keeps an original-order coupon after that customer has already used it', function () {
    $left = pricedSplitVariant(1000);
    $right = pricedSplitVariant(4000);
    $order = splittableOrder([
        ['variant' => $left, 'quantity' => 1, 'price' => 1000],
        ['variant' => $right, 'quantity' => 1, 'price' => 4000],
    ]);
    $lines = $order->productLines()->orderBy('id')->get();

    $coupon = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'ONCE',
        'starts_at' => now()->subMinute(),
        'uses' => 1,
        'max_uses' => 1,
        'max_uses_per_user' => 1,
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);
    attachSplitDiscount($coupon);
    $coupon->users()->attach($order->user_id);
    $order->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $coupon->id,
                'lines' => collect(),
                'total' => new PriceDataType(0, test()->currency, 1),
            ],
        ]),
    ]);

    SplitOrder::run($order->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1]],
    ], couponTarget: '1', applyCoupon: true));

    $children = Order::query()->where('id', '!=', $order->id)->get();
    $chosen = $children->first(fn (Order $child) => $child->productLines->contains('purchasable_id', $left->id));
    $other = $children->first(fn (Order $child) => $child->productLines->contains('purchasable_id', $right->id));

    expect($chosen->discount_total->value)->toBe(100)
        ->and($chosen->discount_breakdown->pluck('discount_id')->all())->toContain($coupon->id)
        ->and($other->discount_total->value)->toBe(0);
});

it('keeps an automatic line discount on the order that received the product', function () {
    $discounted = pricedSplitVariant(2000);
    $plain = pricedSplitVariant(2000);
    $order = splittableOrder([
        ['variant' => $discounted, 'quantity' => 1, 'price' => 2000],
        ['variant' => $plain, 'quantity' => 1, 'price' => 2000],
    ]);
    $lines = $order->productLines()->orderBy('id')->get();

    $discount = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => null,
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);
    attachSplitDiscount($discount);
    $discount->discountables()->create([
        'discountable_type' => $discounted->getMorphClass(),
        'discountable_id' => $discounted->id,
        'type' => 'limitation',
    ]);

    SplitOrder::run($order, splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1]],
    ]));

    $withDiscount = Order::query()->where('id', '!=', $order->id)->get()->first(
        fn (Order $child) => $child->productLines->contains('purchasable_id', $discounted->id)
    );
    $withoutDiscount = Order::query()->where('id', '!=', $order->id)->get()->first(
        fn (Order $child) => $child->productLines->contains('purchasable_id', $plain->id)
    );

    expect($withDiscount->discount_total->value)->toBe(200)
        ->and($withoutDiscount->discount_total->value)->toBe(0);
});

it('stores placed orders with their own references, addresses, and payment status', function () {
    Event::fake([OrderPlacedEvent::class]);
    Bus::fake([MarkAsNewCustomer::class]);

    $variant = pricedSplitVariant(1500);
    $other = pricedSplitVariant(1500);
    $order = splittableOrder([
        ['variant' => $variant, 'quantity' => 1, 'price' => 1500],
        ['variant' => $other, 'quantity' => 1, 'price' => 1500],
    ]);
    $lines = $order->productLines()->orderBy('id')->get();
    $order->transactions()->save(Transaction::factory()->make([
        'order_id' => $order->id,
        'type' => 'intent',
        'success' => true,
    ]));

    $saved = SplitOrder::run($order->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1], 'payment' => 'cash-on-delivery'],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1], 'payment' => 'stripe-card'],
    ]));

    $children = Order::query()->where('id', '!=', $order->id)->get();
    $offline = $children->first(fn (Order $child) => ($child->meta['payment_option'] ?? null) === 'cash-on-delivery');
    $card = $children->first(fn (Order $child) => ($child->meta['payment_option'] ?? null) === 'stripe-card');

    expect($offline->status)->toBe('payment-offline')
        ->and($card->status)->toBe('awaiting-payment')
        ->and($offline->placed_at)->not->toBeNull()
        ->and($card->placed_at)->not->toBeNull()
        ->and($offline->new_customer)->toBeFalse()
        ->and($offline->reference)->not->toBe($order->reference)
        ->and($card->reference)->not->toBe($offline->reference)
        ->and($offline->notes)->toBe('keep-notes')
        ->and($offline->customer_id)->toBe($order->customer_id)
        ->and($offline->user_id)->toBe($order->user_id)
        ->and($offline->shippingAddress->line_one)->toBe('Ship Street')
        ->and($offline->billingAddress->line_one)->toBe('Bill Street')
        ->and($offline->shippingAddress->id)->not->toBe($order->shippingAddress->id)
        ->and($offline->transactions)->toHaveCount(0)
        ->and($card->transactions)->toHaveCount(0)
        ->and($offline->meta['split_from_order_id'])->toBe($order->id)
        ->and($saved->meta['split_into_order_ids'])->toContain($offline->id, $card->id)
        ->and($order->fresh()->productLines)->toHaveCount(2)
        ->and($offline->meta)->not->toHaveKey('awb')
        ->and($offline->meta)->not->toHaveKey('erp_sent_at');

    $offline->shippingAddress->update(['line_one' => 'Changed Street']);

    expect($order->fresh()->shippingAddress->line_one)->toBe('Ship Street')
        ->and($card->fresh()->shippingAddress->line_one)->toBe('Ship Street');

    Event::assertNotDispatched(OrderPlacedEvent::class);
    Bus::assertNotDispatched(MarkAsNewCustomer::class);
});

it('does not move stock when the order is split, including after stock was already taken', function () {
    Config::set('lunar-frontend.orders.stock.decrease', ['prepare-shipment']);
    Config::set('lunar-frontend.orders.stock.increase', ['canceled']);

    $inStock = pricedSplitVariant(1000, [
        'purchasable' => 'in_stock',
        'stock' => 8,
        'backorder' => 1,
    ]);
    $preorder = pricedSplitVariant(1000, [
        'purchasable' => 'preorder',
        'stock' => 3,
        'backorder' => -2,
    ]);
    $order = splittableOrder([
        ['variant' => $inStock, 'quantity' => 1, 'price' => 1000],
        ['variant' => $preorder, 'quantity' => 1, 'price' => 1000],
    ]);
    $lines = $order->productLines()->orderBy('id')->get();

    SplitOrder::run($order, splitRequest([
        ['key' => '1', 'quantities' => [$lines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$lines[1]->id => 1]],
    ]));

    expect($inStock->fresh()->stock)->toBe(8)
        ->and($inStock->fresh()->backorder)->toBe(1)
        ->and($preorder->fresh()->stock)->toBe(3)
        ->and($preorder->fresh()->backorder)->toBe(-2);

    $takenVariant = pricedSplitVariant(1000, [
        'purchasable' => 'in_stock',
        'stock' => 5,
        'backorder' => 0,
    ]);
    $other = pricedSplitVariant(1000);
    $taken = splittableOrder([
        ['variant' => $takenVariant, 'quantity' => 1, 'price' => 1000],
        ['variant' => $other, 'quantity' => 1, 'price' => 1000],
    ]);
    $takenLines = $taken->productLines()->orderBy('id')->get();

    activity()->enableLogging();
    $taken->update(['status' => 'prepare-shipment']);

    SplitOrder::run($taken->fresh(), splitRequest([
        ['key' => '1', 'quantities' => [$takenLines[0]->id => 1]],
        ['key' => '2', 'quantities' => [$takenLines[1]->id => 1]],
    ]));

    expect($takenVariant->fresh()->stock)->toBe(5)
        ->and($taken->fresh()->status)->toBe('split');

    activity()->disableLogging();
});

it('keeps a bulk unit price unless the current catalog price is chosen', function () {
    $variant = pricedSplitVariant(1000);
    Price::factory()->create([
        'price' => 800,
        'min_quantity' => 5,
        'currency_id' => test()->currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);
    $other = pricedSplitVariant(1000);
    $order = splittableOrder([
        ['variant' => $variant, 'quantity' => 6, 'price' => 800],
        ['variant' => $other, 'quantity' => 1, 'price' => 1000],
    ], shipping: true);
    $line = $order->productLines()->where('purchasable_id', $variant->id)->first();
    $otherLine = $order->productLines()->where('purchasable_id', $other->id)->first();

    $coupon = Discount::factory()->create([
        'type' => AdvancedAmountOff::class,
        'coupon' => 'SAVE10',
        'starts_at' => now()->subMinute(),
        'data' => [
            'fixed_value' => false,
            'percentage' => 10,
        ],
    ]);
    attachSplitDiscount($coupon);
    $order->update([
        'discount_breakdown' => collect([
            (object) [
                'discount_id' => $coupon->id,
                'lines' => collect(),
                'total' => new PriceDataType(0, test()->currency, 1),
            ],
        ]),
    ]);

    $targets = [
        ['key' => '1', 'quantities' => [$line->id => 2, $otherLine->id => 0], 'shipping' => 250],
        ['key' => '2', 'quantities' => [$line->id => 4, $otherLine->id => 1], 'shipping' => 0],
    ];

    $preview = SplitOrder::make()->preview($order->fresh(), splitRequest(
        $targets,
        couponTarget: '1',
        applyCoupon: true,
    ));
    $kept = collect($preview->targets)->firstWhere('key', '1');
    $rest = collect($preview->targets)->firstWhere('key', '2');

    expect($kept->lines[0]['unit_price'])->toBe(800)
        ->and($kept->lines[0]['sub_total'])->toBe(1600)
        ->and($kept->lines[0]['discount'])->toBe(160)
        ->and($kept->shippingMinor)->toBe(250)
        ->and($rest->lines[0]['unit_price'])->toBe(800)
        ->and($rest->lines[0]['discount'])->toBe(0)
        ->and($rest->lines[1]['unit_price'])->toBe(1000);

    $current = SplitOrder::make()->preview($order->fresh(), splitRequest(
        $targets,
        couponTarget: '1',
        applyCoupon: true,
        catalogPricedLineIds: [$line->id],
    ));
    $repriced = collect($current->targets)->firstWhere('key', '1');

    expect($repriced->lines[0]['unit_price'])->toBe(1000)
        ->and($repriced->lines[0]['sub_total'])->toBe(2000)
        ->and($repriced->lines[0]['discount'])->toBe(200)
        ->and($repriced->shippingMinor)->toBe(250);

    SplitOrder::run($order->fresh(), splitRequest(
        $targets,
        couponTarget: '1',
        applyCoupon: true,
    ));

    $children = Order::query()->where('id', '!=', $order->id)->with('lines')->get();
    $chosen = $children->first(fn (Order $child): bool => $child->productLines->contains(
        fn (OrderLine $childLine): bool => (int) $childLine->purchasable_id === $variant->id && (int) $childLine->quantity === 2
    ));
    $bulkLine = $chosen->productLines->first(
        fn (OrderLine $childLine): bool => (int) $childLine->purchasable_id === $variant->id
    );

    expect($bulkLine->unit_price->value)->toBe(800)
        ->and($bulkLine->sub_total->value)->toBe(1600)
        ->and($chosen->discount_total->value)->toBe(160)
        ->and($chosen->shippingLines()->first()->sub_total->value)->toBe(250)
        ->and($chosen->shipping_total->value)->toBe($chosen->shippingLines()->first()->total->value);
});
