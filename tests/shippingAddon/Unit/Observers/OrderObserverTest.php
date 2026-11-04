<?php

uses(TestCase::class);
uses(RefreshDatabase::class);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Lunar\Addons\Shipping\Services\ShippingService;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;
use Lunar\Tests\shippingAddon\TestCase;

beforeEach(function () {
    $this->createLanguages();
    $this->createCurrencies();

    Config::set('lunar.shipping.enabled', true);
    Config::set('lunar.shipping.generate_awb_on_status', 'prepare-shipment');
});

it('does not generate an awb when a waiting order moves to the awb status', function () {
    $order = waitingOrder();

    $service = Mockery::mock(ShippingService::class);
    $service->shouldNotReceive('generateAWB');
    app()->instance(ShippingService::class, $service);

    $order->update(['status' => 'prepare-shipment']);
});

it('generates an awb when an order that is not waiting moves to the awb status', function () {
    $order = Order::factory()->create([
        'status' => 'payment-received',
    ]);

    $service = Mockery::mock(ShippingService::class);
    $service->shouldReceive('generateAWB')->once();
    app()->instance(ShippingService::class, $service);

    $order->update(['status' => 'prepare-shipment']);
});

function waitingOrder(): Order
{
    $order = Order::factory()->create([
        'status' => 'payment-received',
    ]);
    $variant = ProductVariant::factory()->create();

    OrderLine::factory()->create([
        'order_id' => $order->id,
        'purchasable_type' => $variant->getMorphClass(),
        'purchasable_id' => $variant->id,
        'type' => 'physical',
        'meta' => ['preorder' => true],
    ]);

    return $order;
}
