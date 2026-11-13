<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Lunar\Actions\Orders\OrderProductLineChange;
use Lunar\Actions\Orders\UpdateOrderProductLines;
use Lunar\Actions\Orders\UpdateOrderShippingMethod;
use Lunar\Actions\Orders\UpdateOrderShippingPrice;
use Lunar\Base\ShippingModifiers;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Exceptions\Orders\InvalidOrderShippingMethodException;
use Lunar\Exceptions\Orders\InvalidOrderShippingPriceException;
use Lunar\Exceptions\Orders\OrderProductLineEditException;
use Lunar\Facades\ShippingManifest;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Tests\Core\Stubs\OrderShippingMethodModifier;
use Lunar\Tests\Core\Stubs\User;
use Lunar\Tests\Loyalty\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    Language::factory()->create([
        'code' => 'en',
        'default' => true,
    ]);

    $this->actingAs(User::factory()->create());
    Gate::define('sales:manage-orders', fn () => true);
});

/**
 * @return array{order: Order, account: LoyaltyAccount, line: OrderLine}
 */
function loyaltyEditableOrder(int $quantity, int $points, int $earned = 10000): array
{
    $currency = Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);
    $channel = Channel::factory()->create([
        'default' => true,
    ]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'price' => 10000,
        'currency_id' => $currency->id,
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
    ]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'channel_id' => $channel->id,
        'currency_code' => $currency->code,
        'cart_id' => $cart->id,
        'status' => 'awaiting-payment',
        'meta' => [
            'payment_reference' => 'pay-1',
            'loyalty_points_to_redeem' => $points,
            'loyalty_discount_minor' => $points,
        ],
    ]);

    $line = OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'quantity' => $quantity,
        'unit_price' => 10000,
        'unit_quantity' => 1,
        'sub_total' => 10000 * $quantity,
        'discount_total' => 0,
        'tax_total' => 0,
        'total' => 10000 * $quantity,
    ]);

    $account = LoyaltyAccount::factory()->create([
        'customer_id' => $customer->id,
        'balance' => 0,
    ]);

    $ledger = app(LoyaltyLedger::class);
    $ledger->earn($account, $earned, 'earn:order:'.$order->id);
    $ledger->spend($account, $points, LoyaltyEventKey::orderSpend($order->id), [
        'reference' => $order,
    ]);

    return compact('order', 'account', 'line');
}

it('returns points when a save redeems fewer points', function () {
    ['order' => $order, 'account' => $account, 'line' => $line] = loyaltyEditableOrder(quantity: 2, points: 10000);

    $saved = UpdateOrderProductLines::run($order, OrderProductLineChange::setQuantity($line->id, 1));

    expect($saved->meta['loyalty_points_to_redeem'])->toBe(5000)
        ->and($account->fresh()->balance)->toBe(5000);
});

it('returns only the points still redeemed when the edited order is canceled', function () {
    ['order' => $order, 'account' => $account, 'line' => $line] = loyaltyEditableOrder(quantity: 2, points: 10000);

    UpdateOrderProductLines::run($order, OrderProductLineChange::setQuantity($line->id, 1));

    $spend = app(LoyaltyLedger::class)->findByEventKey(LoyaltyEventKey::orderSpend($order->id));

    expect($spend->points)->toBe(5000)
        ->and(collect($spend->meta['allocations'])->sum(fn (array $row): int => (int) $row['points']))->toBe(5000)
        ->and($account->fresh()->balance)->toBe(5000);

    $order->fresh()->update(['status' => 'canceled']);

    expect($account->fresh()->balance)->toBe(10000)
        ->and($spend->fresh()->points)->toBe(0);
});

it('leaves the loyalty balance unchanged when the save is rolled back', function () {
    ['order' => $order, 'account' => $account, 'line' => $line] = loyaltyEditableOrder(quantity: 1, points: 5000);

    expect(fn () => UpdateOrderProductLines::run($order, OrderProductLineChange::remove($line->id)))
        ->toThrow(OrderProductLineEditException::class);

    expect($account->fresh()->balance)->toBe(5000)
        ->and($order->fresh()->meta['loyalty_points_to_redeem'])->toBe(5000);
});

it('keeps redeemed points when a shipping price save leaves the product total unchanged', function () {
    ['order' => $order, 'account' => $account] = loyaltyEditableOrder(quantity: 1, points: 10000);

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'description' => 'Courier',
        'identifier' => 'COURIER',
        'unit_price' => 2000,
        'unit_quantity' => 1,
        'quantity' => 1,
        'sub_total' => 2000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 2000,
    ]);

    $saved = UpdateOrderShippingPrice::run($order->fresh(), 1500);

    expect($saved->meta['loyalty_points_to_redeem'])->toBe(10000)
        ->and($saved->meta['loyalty_discount_minor'])->toBe(10000)
        ->and($account->fresh()->balance)->toBe(0);
});

it('leaves the loyalty balance unchanged when a shipping price save is rejected', function () {
    ['order' => $order, 'account' => $account] = loyaltyEditableOrder(quantity: 1, points: 10000);

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'description' => 'Courier',
        'identifier' => 'COURIER',
        'unit_price' => 2000,
        'unit_quantity' => 1,
        'quantity' => 1,
        'sub_total' => 2000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 2000,
    ]);

    expect(fn () => UpdateOrderShippingPrice::run($order->fresh(), -1))
        ->toThrow(InvalidOrderShippingPriceException::class);

    expect($account->fresh()->balance)->toBe(0)
        ->and($order->fresh()->meta['loyalty_points_to_redeem'])->toBe(10000);
});

it('returns points when a shipping method save redeems fewer points', function () {
    ['order' => $order, 'account' => $account] = loyaltyEditableOrder(quantity: 1, points: 15000, earned: 20000);

    TaxClass::factory()->create(['default' => true]);

    CartAddress::factory()->create([
        'cart_id' => $order->cart_id,
        'type' => 'shipping',
        'shipping_option' => 'PICKUP',
    ]);

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'description' => 'Pickup',
        'identifier' => 'PICKUP',
        'unit_price' => 0,
        'unit_quantity' => 1,
        'quantity' => 1,
        'sub_total' => 0,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 0,
    ]);

    app(ShippingModifiers::class)->add(OrderShippingMethodModifier::class);
    OrderShippingMethodModifier::reset();
    OrderShippingMethodModifier::$options = [
        [
            'identifier' => 'PICKUP',
            'name' => 'Pickup',
            'price' => 0,
            'collect' => false,
            'option' => null,
        ],
        [
            'identifier' => 'COURIER',
            'name' => 'Courier',
            'price' => 0,
            'collect' => false,
            'option' => null,
        ],
    ];

    $saved = UpdateOrderShippingMethod::run($order->fresh(), 'COURIER');

    expect($saved->meta['loyalty_points_to_redeem'])->toBe(10000)
        ->and($account->fresh()->balance)->toBe(10000);

    app(ShippingModifiers::class)->remove(OrderShippingMethodModifier::class);
    OrderShippingMethodModifier::reset();
    ShippingManifest::clearOptions();
});

it('leaves the loyalty balance unchanged when a shipping method save is rejected', function () {
    ['order' => $order, 'account' => $account] = loyaltyEditableOrder(quantity: 1, points: 10000);

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => null,
        'purchasable_id' => null,
        'type' => 'shipping',
        'description' => 'Courier',
        'identifier' => 'COURIER',
        'unit_price' => 2000,
        'unit_quantity' => 1,
        'quantity' => 1,
        'sub_total' => 2000,
        'discount_total' => 0,
        'tax_breakdown' => new TaxBreakdown,
        'tax_total' => 0,
        'total' => 2000,
    ]);

    expect(fn () => UpdateOrderShippingMethod::run($order->fresh(), 'COURIER'))
        ->toThrow(InvalidOrderShippingMethodException::class);

    expect($account->fresh()->balance)->toBe(0)
        ->and($order->fresh()->meta['loyalty_points_to_redeem'])->toBe(10000);
});
