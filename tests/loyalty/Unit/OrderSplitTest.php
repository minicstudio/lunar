<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Lunar\Actions\Orders\SplitOrder;
use Lunar\Actions\Orders\DTOs\SplitOrderPreviewTarget;
use Lunar\Actions\Orders\DTOs\SplitOrderRequest;
use Lunar\Actions\Orders\DTOs\SplitOrderTarget;
use Lunar\Admin\Support\Actions\Orders\SplitOrderAction;
use Lunar\Events\OrderSplitLoyaltyMoving;
use Lunar\Loyalty\Exceptions\LoyaltySplitRejectedException;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Models\LoyaltyTransaction;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;
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
 * @return array{order: Order, account: LoyaltyAccount, lines: Collection<int, OrderLine>}
 */
function loyaltySplitOrder(int $points, int $chosenPrice = 10000, int $otherPrice = 10000): array
{
    $currency = Currency::factory()->create([
        'default' => true,
        'decimal_places' => 2,
    ]);
    $channel = Channel::factory()->create([
        'default' => true,
    ]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $chosen = ProductVariant::factory()->create();
    $other = ProductVariant::factory()->create();

    foreach ([$chosen->id => $chosenPrice, $other->id => $otherPrice] as $variantId => $price) {
        Price::factory()->create([
            'price' => $price,
            'currency_id' => $currency->id,
            'priceable_type' => $chosen->getMorphClass(),
            'priceable_id' => $variantId,
        ]);
    }

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'channel_id' => $channel->id,
        'currency_code' => $currency->code,
        'status' => 'awaiting-payment',
        'meta' => [
            'payment_option' => 'cash-on-delivery',
            'loyalty_points_to_redeem' => $points,
            'loyalty_discount_minor' => $points,
        ],
    ]);

    $lines = collect([
        OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => $chosen->getMorphClass(),
            'purchasable_id' => $chosen->id,
            'type' => 'physical',
            'quantity' => 1,
            'unit_price' => $chosenPrice,
            'unit_quantity' => 1,
            'sub_total' => $chosenPrice,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => $chosenPrice,
        ]),
        OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => $other->getMorphClass(),
            'purchasable_id' => $other->id,
            'type' => 'physical',
            'quantity' => 1,
            'unit_price' => $otherPrice,
            'unit_quantity' => 1,
            'sub_total' => $otherPrice,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => $otherPrice,
        ]),
    ]);

    $account = LoyaltyAccount::factory()->create([
        'customer_id' => $customer->id,
        'balance' => 0,
    ]);

    if ($points > 0) {
        $ledger = app(LoyaltyLedger::class);
        $ledger->earn($account, 10000, 'earn:order:'.$order->id);
        $ledger->spend($account, $points, LoyaltyEventKey::orderSpend($order->id), [
            'reference' => $order,
        ]);
    }

    return compact('order', 'account', 'lines');
}

function loyaltySplitRequest(Order $order, bool $applyLoyalty, string $target = '1'): SplitOrderRequest
{
    $lines = $order->productLines()->orderBy('id')->get();

    return new SplitOrderRequest(
        targets: [
            new SplitOrderTarget('1', [$lines[0]->id => 1], 0, 'cash-on-delivery', null),
            new SplitOrderTarget('2', [$lines[1]->id => 1], 0, 'cash-on-delivery', null),
        ],
        couponTarget: null,
        applyCoupon: false,
        loyaltyTarget: $applyLoyalty ? $target : null,
        applyLoyalty: $applyLoyalty,
    );
}

it('moves an existing spend onto the chosen order without spending again', function () {
    ['order' => $order, 'account' => $account] = loyaltySplitOrder(5000);
    $ledger = app(LoyaltyLedger::class);
    $before = LoyaltyTransaction::query()->where('loyalty_account_id', $account->id)->count();

    SplitOrder::run($order, loyaltySplitRequest($order, true));

    $chosen = Order::query()->where('id', '!=', $order->id)->get()->first(
        fn (Order $child): bool => (int) ($child->meta['loyalty_points_to_redeem'] ?? 0) === 5000
    );

    expect($chosen)->not->toBeNull()
        ->and($ledger->findByEventKey(LoyaltyEventKey::orderSpend($order->id)))->toBeNull()
        ->and($ledger->findByEventKey(LoyaltyEventKey::orderSpend($chosen->id))?->reference_id)->toBe($chosen->id)
        ->and($account->fresh()->balance)->toBe(5000)
        ->and(LoyaltyTransaction::query()->where('loyalty_account_id', $account->id)->count())->toBe($before);
});

it('warns before confirm when the chosen order cannot take the spent points', function () {
    ['order' => $order] = loyaltySplitOrder(points: 5000, chosenPrice: 1000);
    $lines = $order->productLines()->orderBy('id')->get();
    $preview = SplitOrder::make()->preview($order, loyaltySplitRequest($order, true));
    $chosen = collect($preview->targets)->first(
        fn (SplitOrderPreviewTarget $target): bool => $target->key === '1',
    );

    expect($chosen)->not->toBeNull()
        ->and($chosen->loyaltyPoints)->toBeLessThan(5000);

    $action = SplitOrderAction::make('split');
    $blockers = (new ReflectionMethod($action, 'confirmBlockers'))->invoke($action, $order, [
        'orders' => [
            ['key' => '1', 'shipping' => '0.00', 'payment_option' => 'cash-on-delivery'],
            ['key' => '2', 'shipping' => '0.00', 'payment_option' => 'cash-on-delivery'],
        ],
        'lines' => [
            ['order_line_id' => $lines[0]->id, 'quantity' => 1, 'target' => '1', 'divided' => false],
            ['order_line_id' => $lines[1]->id, 'quantity' => 1, 'target' => '2', 'divided' => false],
        ],
        'loyalty' => '1',
        'coupon' => 'none',
    ]);

    expect($blockers)->toContain('loyalty_cannot_accept');
});

it('leaves the original spend in place when the chosen order cannot accept every point', function () {
    ['order' => $order, 'account' => $account] = loyaltySplitOrder(points: 5000, chosenPrice: 1000);
    $before = Order::count();

    expect(fn () => SplitOrder::run($order, loyaltySplitRequest($order, true)))
        ->toThrow(LoyaltySplitRejectedException::class);

    expect(Order::count())->toBe($before)
        ->and($order->fresh()->status)->toBe('awaiting-payment')
        ->and(app(LoyaltyLedger::class)->findByEventKey(LoyaltyEventKey::orderSpend($order->id))?->reference_id)->toBe($order->id)
        ->and($account->fresh()->balance)->toBe(5000);
});

it('rejects do not apply when a spend exists and allows it when no spend exists', function () {
    ['order' => $spent, 'account' => $account] = loyaltySplitOrder(5000);

    expect(fn () => SplitOrder::run($spent, loyaltySplitRequest($spent, false)))
        ->toThrow(LoyaltySplitRejectedException::class);

    expect($spent->fresh()->status)->toBe('awaiting-payment')
        ->and(app(LoyaltyLedger::class)->findByEventKey(LoyaltyEventKey::orderSpend($spent->id)))->not->toBeNull()
        ->and($account->fresh()->balance)->toBe(5000);

    ['order' => $plain, 'account' => $empty] = loyaltySplitOrder(0);

    SplitOrder::run($plain, loyaltySplitRequest($plain, false));

    expect(LoyaltyTransaction::query()->where('loyalty_account_id', $empty->id)->count())->toBe(0)
        ->and($plain->fresh()->status)->toBe('split');
});

it('does not dispatch the loyalty event when confirm rolls back', function () {
    ['order' => $order, 'account' => $account] = loyaltySplitOrder(5000);
    $before = Order::count();
    $dispatched = false;

    Event::listen(OrderSplitLoyaltyMoving::class, function () use (&$dispatched): void {
        $dispatched = true;
    });

    Order::created(function (Order $created) use ($order): void {
        if ($created->id !== $order->id) {
            throw new RuntimeException('split failed');
        }
    });

    expect(fn () => SplitOrder::run($order, loyaltySplitRequest($order, true)))
        ->toThrow(RuntimeException::class);

    expect($dispatched)->toBeFalse()
        ->and(Order::count())->toBe($before)
        ->and($account->fresh()->balance)->toBe(5000)
        ->and(app(LoyaltyLedger::class)->findByEventKey(LoyaltyEventKey::orderSpend($order->id))?->reference_id)->toBe($order->id);
});

it('skips the move when loyalty is disabled', function () {
    config(['lunar.loyalty.enabled' => false]);

    ['order' => $order, 'account' => $account] = loyaltySplitOrder(5000);

    SplitOrder::run($order, loyaltySplitRequest($order, true));

    expect($order->fresh()->status)->toBe('split')
        ->and(app(LoyaltyLedger::class)->findByEventKey(LoyaltyEventKey::orderSpend($order->id))?->reference_id)->toBe($order->id)
        ->and($account->fresh()->balance)->toBe(5000);
});
