<?php

use Lunar\Loyalty\Exceptions\InsufficientLoyaltyPointsException;
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

function createRedeemerCart(int $linePrice = 10000, int $quantity = 1): array
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

    $cart->load('lines');
    $cart->calculate();

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

    return compact('cart', 'account', 'customer', 'currency');
}

it('calculates discount minor from points and redeem ratio', function () {
    $redeemer = app(LoyaltyRedeemer::class);

    expect($redeemer->calculateDiscountMinor(500))->toBe(500);
});

it('rejects redemption below minimum points', function () {
    ['cart' => $cart] = createRedeemerCart();

    app(LoyaltyRedeemer::class)->validateRedemption($cart, 50);
})->throws(\InvalidArgumentException::class, 'Minimum redemption is 100 points.');

it('rejects redemption when balance is insufficient', function () {
    ['cart' => $cart, 'account' => $account] = createRedeemerCart();
    $account->earnTransactions()->update(['remaining_points' => 50]);
    $account->update(['balance' => 50]);

    app(LoyaltyRedeemer::class)->validateRedemption($cart, 500);
})->throws(InsufficientLoyaltyPointsException::class);

it('rejects redemption exceeding max redeem percent of eligible subtotal', function () {
    ['cart' => $cart] = createRedeemerCart(linePrice: 10000, quantity: 1);

    app(LoyaltyRedeemer::class)->validateRedemption($cart, 6000);
})->throws(\InvalidArgumentException::class, 'Redemption exceeds maximum allowed discount percentage.');

it('applies and clears loyalty meta on cart', function () {
    ['cart' => $cart] = createRedeemerCart(linePrice: 10000, quantity: 2);

    $redeemer = app(LoyaltyRedeemer::class);
    $cart = $redeemer->applyToCart($cart, 500);

    $meta = $cart->meta?->toArray() ?? (array) $cart->meta;

    expect($meta['loyalty_points_to_redeem'] ?? 0)->toBe(500)
        ->and($meta['loyalty_discount_minor'] ?? 0)->toBe(500);

    $cart = $redeemer->clearFromCart($cart);
    $meta = $cart->meta?->toArray() ?? (array) $cart->meta;

    expect($meta)->not->toHaveKey('loyalty_points_to_redeem')
        ->and($meta)->not->toHaveKey('loyalty_discount_minor');
});
