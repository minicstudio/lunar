<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Lunar\Loyalty\Enums\LoyaltyTransactionType;
use Lunar\Loyalty\Models\LoyaltyAccount;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Tests\ERP\LoyaltyInteractionTestCase;

uses(LoyaltyInteractionTestCase::class);
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->createCurrencies();

    Config::set('lunar.erp.enabled', false);
    Config::set('lunar.erp.smartbill.enabled', false);
    Config::set('lunar.erp.actions.billing', []);
    Config::set('lunar.invoicing.statuses_by_payment_option', [
        'stripe-card' => 'payment-received',
    ]);
});

it('still earns loyalty points when DomPDF invoice marks meta on completed', function () {
    $currency = Currency::where('default', true)->first()
        ?? Currency::factory()->create(['default' => true]);

    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());
    LoyaltyAccount::factory()->create(['customer_id' => $customer->id]);

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 10000,
        'currency_code' => $currency->code,
        'status' => 'payment-received',
        'meta' => ['payment_option' => 'stripe-card'],
    ]);

    $order->update(['status' => 'completed']);

    $order->refresh();
    $account = LoyaltyAccount::where('customer_id', $customer->id)->first();

    expect($order->meta['lunar_invoice_generated_at'] ?? null)->not->toBeNull()
        ->and($account->transactions()->where('type', LoyaltyTransactionType::Earn)->count())->toBe(1)
        ->and($account->balance)->toBe(100);
});
