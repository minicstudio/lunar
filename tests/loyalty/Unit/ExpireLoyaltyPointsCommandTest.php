<?php

use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyLedger;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('expires lots that are past their expiration date', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 500, 'earn:expired', ['expires_at' => now()->subDay()]);
    $ledger->earn($account, 300, 'earn:active', ['expires_at' => now()->addMonth()]);
    $account->refresh();

    expect($account->balance)->toBe(800);

    $this->artisan('loyalty:expire-points')->assertSuccessful();
    $account->refresh();

    expect($account->balance)->toBe(300)
        ->and($account->transactions()->where('type', LoyaltyTransactionType::Expire)->count())->toBe(1);

    $expiredLot = $account->earnTransactions()->where('event_key', 'earn:expired')->first();
    expect($expiredLot->remaining_points)->toBe(0);
});

it('does not expire lots that are not yet expired', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 400, 'earn:active', ['expires_at' => now()->addMonth()]);

    $this->artisan('loyalty:expire-points')->assertSuccessful();
    $account->refresh();

    expect($account->balance)->toBe(400)
        ->and($account->transactions()->where('type', LoyaltyTransactionType::Expire)->exists())->toBeFalse();
});

it('does not expire lots without an expiration date', function () {
    config(['lunar.loyalty.expiration.months' => 0]);

    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 600, 'earn:no-expiry', ['expires_at' => null]);

    $this->artisan('loyalty:expire-points')->assertSuccessful();
    $account->refresh();

    expect($account->balance)->toBe(600)
        ->and($account->transactions()->where('type', LoyaltyTransactionType::Expire)->exists())->toBeFalse();
});

it('is idempotent — running twice does not double-expire', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 200, 'earn:expired', ['expires_at' => now()->subDay()]);

    $this->artisan('loyalty:expire-points')->assertSuccessful();
    $this->artisan('loyalty:expire-points')->assertSuccessful();
    $account->refresh();

    expect($account->balance)->toBe(0)
        ->and($account->transactions()->where('type', LoyaltyTransactionType::Expire)->count())->toBe(1);
});
