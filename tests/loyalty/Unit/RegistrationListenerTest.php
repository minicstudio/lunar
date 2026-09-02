<?php

use Lunar\Loyalty\Calculators\FixedPointsCalculator;
use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Support\LoyaltyEventKey;
use Lunar\Models\Customer;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('awards registration bonus when a customer is created', function () {
    config(['lunar.loyalty.events.registration' => [
        'calculator' => FixedPointsCalculator::class,
        'points' => 250,
    ]]);

    $customer = Customer::factory()->create();

    $account = LoyaltyAccount::where('customer_id', $customer->id)->first();

    expect($account)->not->toBeNull()
        ->and($account->balance)->toBe(250)
        ->and($account->lifetime_earned)->toBe(250);

    $transaction = $account->transactions()->where('type', LoyaltyTransactionType::Earn)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->points)->toBe(250)
        ->and($transaction->event_key)->toBe(LoyaltyEventKey::customerRegistration($customer->id));
});

it('does not award points when registration config is absent', function () {
    config(['lunar.loyalty.events.registration' => null]);

    $customer = Customer::factory()->create();

    expect(LoyaltyAccount::where('customer_id', $customer->id)->exists())->toBeFalse();
});

it('is idempotent — does not double-award on subsequent saves', function () {
    config(['lunar.loyalty.events.registration' => [
        'calculator' => FixedPointsCalculator::class,
        'points' => 100,
    ]]);

    $customer = Customer::factory()->create();
    $customer->touch();

    $account = LoyaltyAccount::where('customer_id', $customer->id)->firstOrFail();

    expect($account->balance)->toBe(100)
        ->and($account->transactions()->count())->toBe(1);
});
