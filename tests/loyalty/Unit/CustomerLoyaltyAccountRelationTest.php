<?php

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Loyalty\Filament\Resources\CustomerResource\RelationManagers\LoyaltyAccountRelationManager;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Models\LoyaltyTransaction;
use Lunar\Models\Customer;
use Lunar\Tests\Loyalty\TestCase;

use function Livewire\invade;

uses(TestCase::class);
uses(RefreshDatabase::class);

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

it('queries only the customers loyalty transactions in the relation table', function () {
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    $account = LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);
    $transactions = LoyaltyTransaction::factory()->count(2)->create(['loyalty_account_id' => $account->id]);
    LoyaltyTransaction::factory()->create();

    $manager = new LoyaltyAccountRelationManager;
    $manager->ownerRecord = $customer;
    $table = $manager->getDefaultTable(Table::make($manager));

    expect($table->evaluate(invade($table)->query))->toBeInstanceOf(Builder::class)
        ->and($table->getQuery()->get()->modelKeys())->toBe($transactions->modelKeys());
});

it('queries an empty transaction table when the customer has no loyalty account', function () {
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    LoyaltyTransaction::factory()->create();

    $manager = new LoyaltyAccountRelationManager;
    $manager->ownerRecord = $customer;
    $table = $manager->getDefaultTable(Table::make($manager));
    $query = $table->getQuery();

    expect($query->getModel())->toBeInstanceOf(LoyaltyTransaction::class)
        ->and($query->get())->toBeEmpty();
});
