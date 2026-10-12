<?php

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ListOrders;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Tag;
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

it('can filter orders by tags', function () {
    $this->asStaff();

    $tag = Tag::factory()->create(['value' => 'VIP']);

    $taggedOrder = Order::factory()->create(['placed_at' => now()]);
    $taggedOrder->tags()->attach($tag);

    $untaggedOrder = Order::factory()->create(['placed_at' => now()]);

    Livewire::test(ListOrders::class)
        ->call('loadTable')
        ->filterTable('tags', [$tag->id])
        ->assertCanSeeTableRecords([$taggedOrder])
        ->assertCanNotSeeTableRecords([$untaggedOrder]);
});

it('shows a no tags found message in the tags filter', function () {
    $this->asStaff();

    Livewire::test(ListOrders::class)
        ->assertTableFilterExists('tags', function (SelectFilter $filter): bool {
            /** @var Select $field */
            $field = $filter->getSchemaComponents()[0];

            return $field->hasInitialNoOptionsMessage()
                && $field->getNoOptionsMessage() === __('lunarpanel::order.table.tags.no_options_message');
        });
});

it('closes the filters dropdown when applying filters', function () {
    $this->asStaff();

    $applyAction = Livewire::test(ListOrders::class)
        ->instance()
        ->getTable()
        ->getFiltersApplyAction();

    expect($applyAction->toHtml())->toContain('x-on:click="close()"');
});
