<?php

use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Models\Customer;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('resolves loyaltyAccount via Eloquent property access', function () {
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $account = LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);

    expect($customer->isRelation('loyaltyAccount'))->toBeTrue()
        ->and($customer->loyaltyAccount)->not->toBeNull()
        ->and($customer->loyaltyAccount->is($account))->toBeTrue();
});

it('returns null loyaltyAccount when the customer has no account', function () {
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());

    expect($customer->loyaltyAccount)->toBeNull();
});
