<?php

use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ListOrders;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Transaction;
use Lunar\Tests\Admin\Feature\Filament\TestCase;

uses(TestCase::class)
    ->group('resource.order');

beforeEach(function () {
    Currency::factory()->create([
        'default' => true,
        'code' => 'GBP',
        'decimal_places' => 2,
    ]);
});

it('excludes orders that have not been placed by default', function () {
    $this->asStaff();

    $placed = Order::factory()->create(['placed_at' => now()]);
    $draft = Order::factory()->create(['placed_at' => null]);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$placed])
        ->assertCanNotSeeTableRecords([$draft]);
});

it('includes not yet placed orders when show_draft_orders is enabled and no date range is set', function () {
    $this->asStaff();

    $placed = Order::factory()->create(['placed_at' => now()]);
    $draft = Order::factory()->create(['placed_at' => null]);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->filterTable('placed_at', [
            'placed_after' => null,
            'placed_before' => null,
            'show_draft_orders' => true,
        ])
        ->assertCanSeeTableRecords([$placed, $draft]);
});

it('ignores show_draft_orders when a placed date range is set', function () {
    $this->asStaff();

    $placed = Order::factory()->create(['placed_at' => now()]);
    $draft = Order::factory()->create(['placed_at' => null]);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->filterTable('placed_at', [
            'placed_after' => now()->subDay()->toDateString(),
            'placed_before' => null,
            'show_draft_orders' => true,
        ])
        ->assertCanSeeTableRecords([$placed])
        ->assertCanNotSeeTableRecords([$draft]);
});

it('can filter orders by payment type', function () {
    $this->asStaff();

    $offlineOrder = Order::factory()->create(['placed_at' => now()]);
    $cardOrder = Order::factory()->create(['placed_at' => now()]);

    Transaction::factory()->create([
        'order_id' => $cardOrder->id,
        'type' => 'capture',
        'success' => true,
        'amount' => $cardOrder->total->value,
    ]);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->filterTable('payment_type', 'offline')
        ->assertCanSeeTableRecords([$offlineOrder])
        ->assertCanNotSeeTableRecords([$cardOrder]);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->filterTable('payment_type', 'card')
        ->assertCanSeeTableRecords([$cardOrder])
        ->assertCanNotSeeTableRecords([$offlineOrder]);
});

it('can filter orders by new customer', function () {
    $this->asStaff();

    $newCustomerOrder = Order::factory()->create([
        'placed_at' => now(),
        'new_customer' => true,
    ]);
    $returningCustomerOrder = Order::factory()->create([
        'placed_at' => now(),
        'new_customer' => false,
    ]);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->filterTable('new_customer', true)
        ->assertCanSeeTableRecords([$newCustomerOrder])
        ->assertCanNotSeeTableRecords([$returningCustomerOrder]);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->filterTable('new_customer', false)
        ->assertCanSeeTableRecords([$returningCustomerOrder])
        ->assertCanNotSeeTableRecords([$newCustomerOrder]);
});

it('can filter orders by total range', function () {
    $this->asStaff();

    $lowTotalOrder = Order::factory()->create([
        'placed_at' => now(),
        'total' => 1000,
    ]);
    $midTotalOrder = Order::factory()->create([
        'placed_at' => now(),
        'total' => 5000,
    ]);
    $highTotalOrder = Order::factory()->create([
        'placed_at' => now(),
        'total' => 10000,
    ]);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->filterTable('total', [
            'total_from' => '20',
            'total_to' => '60',
        ])
        ->assertCanSeeTableRecords([$midTotalOrder])
        ->assertCanNotSeeTableRecords([$lowTotalOrder, $highTotalOrder]);
});
