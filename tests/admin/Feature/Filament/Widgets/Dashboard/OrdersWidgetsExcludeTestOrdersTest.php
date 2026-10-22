<?php

use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrdersByStatusChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrderStatsOverview;
use Lunar\DataTypes\Price;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Tests\Admin\Feature\Filament\TestCase;

uses(TestCase::class)
    ->group('widgets.dashboard');

beforeEach(function () {
    Currency::factory()->create([
        'code' => 'GBP',
        'default' => true,
    ]);

    Order::factory()->create([
        'status' => 'payment-received',
        'placed_at' => now(),
        'total' => 10000,
        'shipping_total' => 0,
    ]);

    Order::factory()->create([
        'status' => 'test-order',
        'placed_at' => now(),
        'total' => 50000,
        'shipping_total' => 0,
    ]);
});

function callProtectedWidgetMethod(object $widget, string $method): mixed
{
    return (new ReflectionMethod($widget, $method))->invoke($widget);
}

it('does not count test orders in the dashboard order stats', function () {
    $stats = callProtectedWidgetMethod(new OrderStatsOverview, 'getStats');

    $todayCount = $stats[0];
    $todayTotal = $stats[3];

    expect($todayCount->getValue())->toBe('1')
        ->and($todayTotal->getValue())->toBe((new Price(10000, Currency::getDefault()))->formatted);
});

it('does not show a test order series in the orders by status chart', function () {
    $options = callProtectedWidgetMethod(new OrdersByStatusChart, 'getOptions');

    expect(collect($options['series'])->pluck('name')->all())
        ->toContain('Payment Received')
        ->not->toContain('Test Order');
});
