<?php

use Livewire\Livewire;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ListOrders;
use Lunar\Models\Currency;
use Lunar\Models\Order;

uses(\Lunar\Tests\Admin\Feature\Filament\TestCase::class)
    ->group('resource.order');

beforeEach(function () {
    Currency::factory()->create([
        'default' => true,
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
