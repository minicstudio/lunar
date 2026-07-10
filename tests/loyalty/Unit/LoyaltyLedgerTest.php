<?php

use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Loyalty\Services\LoyaltyLedger;
use Lunar\Loyalty\Support\LoyaltyEventKey;

uses(\Lunar\Tests\Loyalty\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('keeps cached balance consistent after earn spend expire and adjust', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 1000, LoyaltyEventKey::adjust());
    $account->refresh();

    expect($ledger->assertBalanceConsistent($account))->toBeTrue()
        ->and($ledger->assertLotsConsistent($account))->toBeTrue()
        ->and($account->getAvailableBalance())->toBe(1000);

    $ledger->spend($account, 400, LoyaltyEventKey::adjust());
    $account->refresh();

    expect($ledger->assertBalanceConsistent($account))->toBeTrue()
        ->and($account->balance)->toBe(600)
        ->and($account->getAvailableBalance())->toBe(600);

    $earnLot = $account->earnTransactions()->first();
    $ledger->expire($earnLot, 'expire:'.$earnLot->id);
    $account->refresh();

    expect($ledger->assertBalanceConsistent($account))->toBeTrue()
        ->and($account->balance)->toBe(0);
});

it('is idempotent via event_key', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();
    $eventKey = LoyaltyEventKey::customerRegistration(1);

    $first = $ledger->earn($account, 500, $eventKey);
    $second = $ledger->earn($account, 500, $eventKey);
    $account->refresh();

    expect($first->id)->toBe($second->id)
        ->and($account->balance)->toBe(500)
        ->and($account->transactions()->count())->toBe(1);
});

it('allocates spend using fifo ordering', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 300, 'earn:1');
    $ledger->earn($account, 500, 'earn:2');
    $account->refresh();

    $lots = $account->earnTransactions()->orderBy('id')->get();
    $lots[0]->update(['created_at' => now()->subDay()]);
    $lots[1]->update(['created_at' => now()]);

    $spend = $ledger->spend($account, 400, 'spend:1');
    $lots[0]->refresh();
    $lots[1]->refresh();

    expect($lots[0]->remaining_points)->toBe(0)
        ->and($lots[1]->remaining_points)->toBe(400)
        ->and($spend->meta['allocations'])->toHaveCount(2);
});

it('restores lots on positive adjust from spend reversal', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 1000, 'earn:1');
    $spend = $ledger->spend($account, 600, 'spend:1');
    $account->refresh();

    $ledger->adjust($account, 600, 'cancel:1', ['spend_transaction' => $spend]);
    $account->refresh();

    expect($account->balance)->toBe(1000)
        ->and($account->getAvailableBalance())->toBe(1000)
        ->and($ledger->assertBalanceConsistent($account))->toBeTrue();
});

it('rejects spend when insufficient lot balance', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 100, 'earn:1');

    $ledger->spend($account, 200, 'spend:1');
})->throws(\Lunar\Loyalty\Exceptions\InsufficientLoyaltyPointsException::class);

it('uses signed points for negative adjust clawback', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $earn = $ledger->earn($account, 1000, LoyaltyEventKey::orderEarn(1));
    $ledger->adjust($account, -250, LoyaltyEventKey::orderRefund(1, 1), [
        'earn_transaction' => $earn,
    ]);
    $account->refresh();

    $adjust = $account->transactions()->where('type', LoyaltyTransactionType::Adjust)->first();

    expect($adjust->points)->toBe(-250)
        ->and($account->balance)->toBe(750)
        ->and($ledger->aggregateBalanceFromLedger($account))->toBe(750);
});

it('returns lot sum when cache is corrupted', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 800, 'earn:1');
    $account->forceFill(['balance' => 1000])->save();
    $account->refresh();

    expect($account->getBalanceForDisplay())->toBe(1000)
        ->and($account->getAvailableBalance())->toBe(800);
});

it('reduces lifetime_spent when cancelled order spend is reversed', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $ledger->earn($account, 500, 'earn:registration');
    $spend = $ledger->spend($account, 100, LoyaltyEventKey::orderSpend(1661));
    $account->refresh();

    expect($account->lifetime_spent)->toBe(100);

    $ledger->adjust($account, 100, LoyaltyEventKey::orderCancelSpend(1661), [
        'spend_transaction' => $spend,
        'meta' => ['reason' => 'order_cancelled'],
    ]);
    $account->refresh();

    expect($account->balance)->toBe(500)
        ->and($account->lifetime_spent)->toBe(0)
        ->and($ledger->aggregateLifetimeSpentFromLedger($account))->toBe(0);
});

it('reduces lifetime_earned when completed order earn is clawed back on cancel', function () {
    $ledger = app(LoyaltyLedger::class);
    $account = LoyaltyAccount::factory()->create();

    $earn = $ledger->earn($account, 87, LoyaltyEventKey::orderEarn(1660));
    $account->refresh();

    expect($account->lifetime_earned)->toBe(87);

    $ledger->adjust($account, -87, LoyaltyEventKey::orderCancelEarn(1660), [
        'earn_transaction' => $earn,
        'meta' => ['reason' => 'order_cancelled'],
    ]);
    $account->refresh();

    expect($account->balance)->toBe(0)
        ->and($account->lifetime_earned)->toBe(0)
        ->and($ledger->aggregateLifetimeEarnedFromLedger($account))->toBe(0);
});
