<?php

use Lunar\Admin\Filament\Resources\CustomerResource\Widgets\CustomerStatsOverviewWidget;
use Lunar\DataTypes\Price;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Order;
use Lunar\Tests\Admin\Feature\Filament\TestCase;

uses(TestCase::class)
    ->group('resource.customer');

it('does not include test orders in the customer stats', function () {
    $currency = Currency::factory()->create([
        'code' => 'GBP',
        'default' => true,
    ]);

    $customer = Customer::factory()->create();

    foreach ([1000, 3000] as $subTotal) {
        Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'payment-received',
            'sub_total' => $subTotal,
            'exchange_rate' => 1,
        ]);
    }

    Order::factory()->create([
        'customer_id' => $customer->id,
        'status' => 'test-order',
        'sub_total' => 90000,
        'exchange_rate' => 1,
    ]);

    $widget = new CustomerStatsOverviewWidget;
    $widget->record = $customer;

    [$totalOrders, $avgSpend, $totalSpend] = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect($totalOrders->getValue())->toBe(2)
        ->and($avgSpend->getValue())->toBe((new Price(2000, $currency))->formatted)
        ->and($totalSpend->getValue())->toBe((new Price(4000, $currency))->formatted);
});
