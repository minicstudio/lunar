<?php

use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Exceptions\InsufficientLoyaltyPointsException;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyEngine;
use Lunar\Loyalty\Services\LoyaltyLedger;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('positive manual adjust writes an earn transaction so points are spendable', function () {
    $account = LoyaltyAccount::factory()->create();

    app(LoyaltyEngine::class)->manualAdjust($account, 500, 'staff credit');
    $account->refresh();

    $transaction = $account->transactions()->where('type', LoyaltyTransactionType::Earn)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->points)->toBe(500)
        ->and($transaction->remaining_points)->toBe(500)
        ->and($account->balance)->toBe(500)
        ->and($account->lifetime_earned)->toBe(500)
        ->and($account->getAvailableBalance())->toBe(500);
});

it('negative manual adjust writes an adjust transaction and does not increment lifetime_spent', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 1000, 'earn:1');
    $account->refresh();

    app(LoyaltyEngine::class)->manualAdjust($account, -300, 'staff debit');
    $account->refresh();

    $transaction = $account->transactions()->where('type', LoyaltyTransactionType::Adjust)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->points)->toBe(-300)
        ->and($account->balance)->toBe(700)
        ->and($account->lifetime_spent)->toBe(0);
});

it('negative manual adjust decrements FIFO lots', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 1000, 'earn:1');
    $lot = $account->earnTransactions()->first();

    app(LoyaltyEngine::class)->manualAdjust($account, -400, 'correction');
    $lot->refresh();

    expect($lot->remaining_points)->toBe(600)
        ->and(app(LoyaltyLedger::class)->assertLotsConsistent($account->refresh()))->toBeTrue();
});

it('negative manual adjust is rejected when points exceed available balance', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 200, 'earn:1');

    app(LoyaltyEngine::class)->manualAdjust($account, -500, 'over-debit');
})->throws(InsufficientLoyaltyPointsException::class);

it('returns null when points are zero', function () {
    $account = LoyaltyAccount::factory()->create();

    $result = app(LoyaltyEngine::class)->manualAdjust($account, 0, 'noop');

    expect($result)->toBeNull();
});
