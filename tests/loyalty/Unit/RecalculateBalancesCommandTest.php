<?php

use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyLedger;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('reports cache vs ledger mismatches', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 500, 'earn:1');
    $account->forceFill(['balance' => 900])->save();

    $this->artisan('loyalty:recalculate-balances')
        ->assertExitCode(1);
});

it('fixes cached balance with --fix flag', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 500, 'earn:1');
    $account->forceFill(['balance' => 900])->save();

    $this->artisan('loyalty:recalculate-balances', ['--fix' => true])
        ->assertExitCode(0);

    expect($account->fresh()->balance)->toBe(500);
});

it('fixes stale lifetime_spent after cancelled order spend reversal', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 500, 'earn:registration');
    $spend = $ledger->spend($account, 100, \Lunar\Loyalty\Support\LoyaltyEventKey::orderSpend(1661));
    $ledger->adjust($account, 100, \Lunar\Loyalty\Support\LoyaltyEventKey::orderCancelSpend(1661), [
        'spend_transaction' => $spend,
        'meta' => ['reason' => 'order_cancelled'],
    ]);

    $account->forceFill(['lifetime_spent' => 100])->save();

    $this->artisan('loyalty:recalculate-balances', ['--fix' => true])
        ->assertExitCode(0);

    expect($account->fresh()->lifetime_spent)->toBe(0);
});
