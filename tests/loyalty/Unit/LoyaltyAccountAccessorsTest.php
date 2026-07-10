<?php

use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyLedger;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('exposes display_balance and available_balance accessors', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 750, 'earn:1');
    $account->refresh();

    expect($account->display_balance)->toBe($account->getBalanceForDisplay())
        ->and($account->available_balance)->toBe($account->getAvailableBalance())
        ->and($account->display_balance)->toBe(750)
        ->and($account->available_balance)->toBe(750);
});
