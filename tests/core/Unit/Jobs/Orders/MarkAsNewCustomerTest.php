<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Lunar\Jobs\Orders\MarkAsNewCustomer;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Tests\Core\TestCase;

uses(TestCase::class);

uses(RefreshDatabase::class);

test('can correctly mark order for new customer', function () {
    Currency::factory()->create([
        'default' => true,
    ]);

    $order = Order::factory()->create([
        'new_customer' => false,
        'placed_at' => now()->subYear(),
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'contact_email' => 'customer@site.com',
        'type' => 'billing',
    ]);

    MarkAsNewCustomer::dispatchSync($order->id);

    expect($order->refresh()->new_customer)->toBeTrue();

    $order = Order::factory()->create([
        'new_customer' => false,
        'placed_at' => now(),
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'contact_email' => 'customer@site.com',
        'type' => 'billing',
    ]);

    MarkAsNewCustomer::dispatchSync($order->id);

    expect($order->refresh()->new_customer)->toBeFalse();
});

test('does not treat an unplaced order with the same email as a previous purchase', function () {
    Currency::factory()->create([
        'default' => true,
    ]);

    $draft = Order::factory()->create([
        'new_customer' => false,
        'placed_at' => null,
    ]);

    OrderAddress::factory()->create([
        'order_id' => $draft->id,
        'contact_email' => 'customer@site.com',
        'type' => 'billing',
    ]);

    $order = Order::factory()->create([
        'new_customer' => false,
        'placed_at' => now(),
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'contact_email' => 'customer@site.com',
        'type' => 'billing',
    ]);

    MarkAsNewCustomer::dispatchSync($order->id);

    expect($order->refresh()->new_customer)->toBeTrue();
});

test('does not treat a blank billing email as a shared customer', function () {
    Currency::factory()->create([
        'default' => true,
    ]);

    $previous = Order::factory()->create([
        'new_customer' => true,
        'placed_at' => now()->subDay(),
    ]);

    OrderAddress::factory()->create([
        'order_id' => $previous->id,
        'contact_email' => '',
        'type' => 'billing',
    ]);

    $order = Order::factory()->create([
        'new_customer' => false,
        'placed_at' => now(),
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'contact_email' => '',
        'type' => 'billing',
    ]);

    MarkAsNewCustomer::dispatchSync($order->id);

    expect($order->refresh()->new_customer)->toBeTrue();
});

test('marks the customer when the order is first placed', function () {
    Currency::factory()->create([
        'default' => true,
    ]);

    $order = Order::factory()->create([
        'new_customer' => false,
        'placed_at' => null,
    ]);

    OrderAddress::factory()->create([
        'order_id' => $order->id,
        'contact_email' => 'first@site.com',
        'type' => 'billing',
    ]);

    $order->update([
        'placed_at' => now(),
    ]);

    expect($order->refresh()->new_customer)->toBeTrue();

    Bus::fake([MarkAsNewCustomer::class]);

    $order->update([
        'placed_at' => now()->addMinute(),
    ]);

    Bus::assertNotDispatched(MarkAsNewCustomer::class);
});
