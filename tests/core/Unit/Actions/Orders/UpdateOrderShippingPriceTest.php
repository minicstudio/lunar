<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Lunar\Actions\Orders\UpdateOrderShippingPrice;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Base\ValueObjects\Cart\TaxBreakdownAmount;
use Lunar\DataTypes\Price as PriceDataType;
use Lunar\DataTypes\ShippingOption;
use Lunar\DiscountTypes\AdvancedAmountOff;
use Lunar\Exceptions\Orders\InvalidOrderShippingPriceException;
use Lunar\Exceptions\Orders\LockedOrderProductLineEditException;
use Lunar\Exceptions\Orders\MissingOrderCartException;
use Lunar\Exceptions\Orders\UnauthorizedOrderProductLineEditException;
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
    $taxRate = TaxRate::factory()->create();

    TaxRateAmount::factory()->create([
        'percentage' => 19,
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
    ]);
});

/**
 * @param  array<string, mixed>  $shippingMeta
 * @return array{order: Order, cart: Cart, variant: ProductVariant, line: OrderLine, shipping: OrderLine}
 */
function shippingPriceOrder(int $shippingMinor = 2000, array $shippingMeta = []): array
{
    $variant = ProductVariant::factory()->create(['stock' => 8]);

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
        'meta' => ['payment_reference' => 'pay-1'],
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
        'description' => 'Courier',
        'identifier' => 'COURIER',
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

    return compact('order', 'cart', 'variant', 'line', 'shipping');
}

it('stores 15.00 as the shipping price and moves the order total by the same difference', function (bool $pricesIncludeTax) {
    Config::set('lunar.pricing.stored_inclusive_of_tax', $pricesIncludeTax);

    ['order' => $order] = shippingPriceOrder();
    $normalized = UpdateOrderShippingPrice::run($order, 2000);
    $beforeShipping = $normalized->shipping_total->value;
    $beforeTotal = $normalized->total->value;

    $saved = UpdateOrderShippingPrice::run($normalized, 1500);
    $shippingLine = $saved->shippingLines()->first();
    $shippingDifference = $saved->shipping_total->value - $beforeShipping;

    expect($shippingLine->unit_price->value)->toBe(1500)
        ->and($shippingLine->sub_total->value)->toBe(1500)
        ->and($shippingLine->identifier)->toBe('COURIER')
        ->and($shippingLine->description)->toBe('Courier')
        ->and($shippingLine->meta['shipping_price_override'])->toBeTrue()
        ->and($saved->total->value - $beforeTotal)->toBe($shippingDifference);

    if ($pricesIncludeTax) {
        expect($saved->shipping_total->value)->toBe(1500);

        return;
    }

    expect($saved->shipping_total->value)->toBe(1785)
        ->and($shippingLine->tax_total->value)->toBe(285);
})->with([
    'prices include tax' => true,
    'prices exclude tax' => false,
]);

it('stores a shipping price of zero', function () {
    ['order' => $order] = shippingPriceOrder();

    $saved = UpdateOrderShippingPrice::run($order, 0);
    $shippingLine = $saved->shippingLines()->first();

    expect($shippingLine->unit_price->value)->toBe(0)
        ->and($shippingLine->sub_total->value)->toBe(0)
        ->and($saved->shipping_total->value)->toBe(0);
});

it('leaves the order unchanged when the price cannot be saved', function (string $case) {
    ['order' => $order, 'shipping' => $shipping, 'cart' => $cart] = shippingPriceOrder();
    $beforeTotal = $order->fresh()->total->value;
    $beforeUnit = $shipping->unit_price->value;

    match ($case) {
        'negative' => null,
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
        default => InvalidOrderShippingPriceException::class,
    };

    $amount = $case === 'negative' ? -1 : 1500;

    expect(fn () => UpdateOrderShippingPrice::run($order->fresh(), $amount))
        ->toThrow($exception);

    $order->refresh();

    expect($order->total->value)->toBe($beforeTotal)
        ->and($order->shippingLines()->count())->toBe($case === 'no shipping line' ? 0 : ($case === 'two shipping lines' ? 2 : 1))
        ->and($cart->fresh()->lines()->count())->toBe(0);

    if ($case !== 'no shipping line') {
        expect($order->shippingLines()->first()->unit_price->value)->toBe($beforeUnit);
    }
})->with([
    'negative',
    'captured',
    'unauthorized',
    'missing cart',
    'no shipping line',
    'two shipping lines',
]);

it('keeps a waiting order in consultation and clears the pending shipping marker', function () {
    ['order' => $order, 'line' => $line, 'shipping' => $shipping] = shippingPriceOrder(shippingMeta: [
        'preorder_shipping_pending' => true,
        'carrier' => 'kept',
    ]);

    $order->update([
        'status' => 'awaiting-phone-consultation',
        'meta' => [
            'payment_reference' => 'pay-1',
            'payment_option' => 'cash-on-delivery',
        ],
    ]);
    $line->update([
        'meta' => ['preorder' => true],
    ]);

    $saved = UpdateOrderShippingPrice::run($order->fresh(), 1500);
    $shippingLine = $saved->shippingLines()->first();

    expect($saved->status)->toBe('awaiting-phone-consultation')
        ->and($saved->meta['payment_option'])->toBe('cash-on-delivery')
        ->and($saved->awaitsPreorderConsultation())->toBeTrue()
        ->and($shippingLine->meta['shipping_price_override'])->toBeTrue()
        ->and($shippingLine->meta['carrier'])->toBe('kept')
        ->and(isset($shippingLine->meta['preorder_shipping_pending']))->toBeFalse()
        ->and($shipping->fresh()->id)->toBe($shippingLine->id);
});

it('keeps the ordered product price when the catalog price has changed', function () {
    ['order' => $order, 'variant' => $variant, 'line' => $line] = shippingPriceOrder();
    $variant->prices()->update(['price' => 4500]);

    $saved = UpdateOrderShippingPrice::run($order, 1500);
    $productLine = $saved->productLines()->first();

    expect($productLine->id)->toBe($line->id)
        ->and($productLine->unit_price->value)->toBe(1000)
        ->and($productLine->sub_total->value)->toBe(1000)
        ->and($productLine->quantity)->toBe(1);
});

it('taxes shipping for the order address when the cart address is in another country', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', false);

    $home = Country::factory()->create();
    $destination = Country::factory()->create();
    $defaultZone = TaxZone::query()->where('default', true)->first();

    TaxZoneCountry::factory()->create([
        'tax_zone_id' => $defaultZone->id,
        'country_id' => $home->id,
    ]);

    $destinationZone = TaxZone::factory()->create([
        'default' => false,
        'active' => true,
        'zone_type' => 'country',
    ]);
    TaxZoneCountry::factory()->create([
        'tax_zone_id' => $destinationZone->id,
        'country_id' => $destination->id,
    ]);
    $destinationRate = TaxRate::factory()->create([
        'tax_zone_id' => $destinationZone->id,
    ]);
    TaxRateAmount::factory()->create([
        'percentage' => 5,
        'tax_class_id' => TaxClass::getDefault()->id,
        'tax_rate_id' => $destinationRate->id,
    ]);

    ['order' => $order, 'cart' => $cart] = shippingPriceOrder();

    $cart->update(['tax_zone_id' => $defaultZone->id]);
    CartAddress::factory()->create([
        'cart_id' => $cart->id,
        'type' => 'shipping',
        'country_id' => $home->id,
        'postcode' => 'HOME1',
        'state' => null,
        'shipping_option' => 'COURIER',
    ]);
    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'type' => 'shipping',
        'country_id' => $destination->id,
        'postcode' => 'DEST1',
        'state' => null,
    ]);

    $saved = UpdateOrderShippingPrice::run($order->fresh(), 10000);
    $shippingLine = $saved->shippingLines()->first();

    expect($shippingLine->sub_total->value)->toBe(10000)
        ->and($shippingLine->tax_total->value)->toBe(500)
        ->and($saved->shipping_total->value)->toBe(10500);
});

it('keeps a zero rated shipping line at zero when its tax rate no longer matches', function () {
    Config::set('lunar.pricing.stored_inclusive_of_tax', false);

    $zeroRate = TaxRate::factory()->create([
        'tax_zone_id' => TaxZone::query()->where('default', true)->value('id'),
    ]);
    $zeroAmount = TaxRateAmount::factory()->create([
        'percentage' => 0,
        'tax_class_id' => TaxClass::factory()->create(['default' => false])->id,
        'tax_rate_id' => $zeroRate->id,
    ]);

    ['order' => $order, 'shipping' => $shipping] = shippingPriceOrder();
    $shipping->update([
        'tax_breakdown' => new TaxBreakdown(collect([
            new TaxBreakdownAmount(
                price: new PriceDataType(0, test()->currency, 1),
                identifier: 'tax_rate_'.$zeroRate->id,
                description: 'Zero',
                percentage: 0,
            ),
        ])),
    ]);
    $zeroAmount->delete();

    $saved = UpdateOrderShippingPrice::run($order->fresh(), 1500);
    $shippingLine = $saved->shippingLines()->first();

    expect($shippingLine->sub_total->value)->toBe(1500)
        ->and($shippingLine->tax_total->value)->toBe(0)
        ->and($saved->shipping_total->value)->toBe(1500)
        ->and((float) $shippingLine->tax_breakdown->amounts->first()->percentage)->toBe(0.0);
});

it('keeps a shipping line note', function () {
    ['order' => $order, 'shipping' => $shipping] = shippingPriceOrder();
    $shipping->update(['notes' => 'Leave at the door']);

    $saved = UpdateOrderShippingPrice::run($order, 1500);

    expect($saved->shippingLines()->first()->id)->toBe($shipping->id)
        ->and($saved->shippingLines()->first()->notes)->toBe('Leave at the door');
});

it('does not change variant stock', function () {
    ['order' => $order, 'variant' => $variant] = shippingPriceOrder();

    UpdateOrderShippingPrice::run($order, 1500);

    expect($variant->fresh()->stock)->toBe(8);
});

it('does not waive an administrator shipping price for a free shipping coupon', function () {
    ['order' => $order, 'cart' => $cart] = shippingPriceOrder();

    ShippingManifest::addOption(new ShippingOption(
        name: 'Courier',
        description: 'Courier',
        identifier: 'COURIER',
        price: new PriceDataType(1990, test()->currency, 1),
        taxClass: TaxClass::getDefault(),
    ));

    CartAddress::factory()->create([
        'cart_id' => $cart->id,
        'type' => 'shipping',
        'shipping_option' => 'COURIER',
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

    $saved = UpdateOrderShippingPrice::run($order->fresh(), 1200);
    $shippingLine = $saved->shippingLines()->first();

    expect($shippingLine->sub_total->value)->toBe(1200)
        ->and($shippingLine->discount_total->value)->toBe(0)
        ->and($saved->discount_total->value)->toBeGreaterThan(0);
});
