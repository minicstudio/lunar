<?php

use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Models\LoyaltyTransaction;
use Lunar\Loyalty\Services\LoyaltyRedeemer;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function createPipelineCart(int $linePrice = 10000, int $quantity = 2): Cart
{
    $currency = Currency::factory()->create(['default' => true, 'decimal_places' => 2]);
    $channel = Channel::factory()->create(['default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $variant = ProductVariant::factory()->create();

    Price::factory()->create([
        'priceable_type' => $variant->getMorphClass(),
        'priceable_id' => $variant->id,
        'currency_id' => $currency->id,
        'price' => $linePrice,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'quantity' => $quantity,
    ]);

    $account = LoyaltyAccount::factory()->create([
        'customer_id' => $customer->id,
        'balance' => 10000,
    ]);

    LoyaltyTransaction::factory()->create([
        'loyalty_account_id' => $account->id,
        'points' => 10000,
        'remaining_points' => 10000,
        'event_key' => LoyaltyEventKey::adjust(),
        'expires_at' => now()->addYear(),
    ]);

    return $cart->refresh()->load('lines')->calculate();
}

it('clears loyalty redemption when capped points fall below minimum', function () {
    $currency = Currency::factory()->create(['default' => true, 'decimal_places' => 2]);
    $channel = Channel::factory()->create(['default' => true]);
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $expensiveVariant = ProductVariant::factory()->create();
    $cheapVariant = ProductVariant::factory()->create();

    Price::factory()->create([
        'priceable_type' => $expensiveVariant->getMorphClass(),
        'priceable_id' => $expensiveVariant->id,
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    Price::factory()->create([
        'priceable_type' => $cheapVariant->getMorphClass(),
        'priceable_id' => $cheapVariant->id,
        'currency_id' => $currency->id,
        'price' => 20,
    ]);

    $cart = Cart::factory()->create([
        'currency_id' => $currency->id,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $expensiveVariant->getMorphClass(),
        'purchasable_id' => $expensiveVariant->id,
        'quantity' => 2,
    ]);

    $cart->lines()->create([
        'purchasable_type' => $cheapVariant->getMorphClass(),
        'purchasable_id' => $cheapVariant->id,
        'quantity' => 1,
    ]);

    $account = LoyaltyAccount::factory()->create([
        'customer_id' => $customer->id,
        'balance' => 10000,
    ]);

    LoyaltyTransaction::factory()->create([
        'loyalty_account_id' => $account->id,
        'points' => 10000,
        'remaining_points' => 10000,
        'event_key' => LoyaltyEventKey::adjust(),
        'expires_at' => now()->addYear(),
    ]);

    $cart = $cart->refresh()->load('lines')->calculate();
    $cart = app(LoyaltyRedeemer::class)->applyToCart($cart, 300);

    $cart->lines()->where('purchasable_id', $expensiveVariant->id)->delete();
    $cart = $cart->refresh()->load('lines')->calculate();

    $meta = $cart->meta?->toArray() ?? (array) $cart->meta;

    expect($meta)->not->toHaveKey('loyalty_points_to_redeem')
        ->and($meta)->not->toHaveKey('loyalty_discount_minor')
        ->and($cart->loyaltyTotalIncTax)->toBeNull();
});

it('auto-caps loyalty points in meta when cart subtotal shrinks', function () {
    $cart = createPipelineCart(linePrice: 10000, quantity: 2);
    $redeemer = app(LoyaltyRedeemer::class);

    $cart = $redeemer->applyToCart($cart, 1000);
    $meta = $cart->meta?->toArray() ?? (array) $cart->meta;

    expect($meta['loyalty_points_to_redeem'] ?? 0)->toBe(1000);

    $cart->lines()->update(['quantity' => 1]);
    $cart = $cart->refresh()->load('lines')->calculate();
    $meta = $cart->meta?->toArray() ?? (array) $cart->meta;

    expect($meta['loyalty_points_to_redeem'] ?? 0)->toBeLessThanOrEqual(5000);
});

it('sets loyalty total inc tax and non-negative cart total after pipeline', function () {
    $cart = createPipelineCart(linePrice: 10000, quantity: 1);
    $cart = app(LoyaltyRedeemer::class)->applyToCart($cart, 500);

    expect($cart->loyaltyTotalIncTax?->value)->toBeGreaterThan(0)
        ->and($cart->total?->value)->toBeGreaterThanOrEqual(0);
});

it('prevents duplicate spend for the same order event key', function () {
    $account = LoyaltyAccount::factory()->create(['balance' => 0]);
    $ledger = app(\Lunar\Loyalty\Services\LoyaltyLedger::class);

    $ledger->earn($account, 1000, 'earn:setup');
    $account->refresh();

    $first = $ledger->spend($account, 400, LoyaltyEventKey::orderSpend(99));
    $second = $ledger->spend($account, 400, LoyaltyEventKey::orderSpend(99));
    $account->refresh();

    expect($first->id)->toBe($second->id)
        ->and($account->balance)->toBe(600)
        ->and($account->transactions()->where('type', \Lunar\Loyalty\Enums\LoyaltyTransactionType::Spend)->count())->toBe(1);
});
