<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Lunar\ERP\Providers\Magister\MagisterApiClient;
use Lunar\ERP\Providers\Magister\MagisterErpExporter;
use Lunar\Models\Country;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

beforeEach(function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();
});

it('sendOrder returns true when client returns success true', function () {
    $mockApi = \Mockery::mock(MagisterApiClient::class);
    $mockApi->shouldReceive('sendOrder')->once()->andReturn(['success' => true]);

    $exporter = new MagisterErpExporter($mockApi);
    $user = $this->createUser();
    $country = Country::factory()->create();
    $customer = Customer::factory()->create();
    $group = CustomerGroup::where('default', true)->first();
    $customer->customerGroups()->attach($group->id);
    $customer->users()->attach($user->id);
    $order = Order::factory()
        ->for($customer)
        ->for($user)
        ->has(OrderAddress::factory()->state([
            'type' => 'billing',
            'contact_email' => 'b@example.com',
            'line_one' => 'L1',
            'city' => 'Cluj',
            'state' => 'Cluj',
            'country_id' => $country->id,
        ]), 'billingAddress')
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'first_name' => 'J',
            'last_name' => 'D',
            'city' => 'Arad',
            'postcode' => '310000',
            'contact_phone' => '+407',
            'contact_email' => 's@example.com',
            'line_one' => 'S1',
            'country_id' => $country->id,
        ]), 'shippingAddress')
        ->create(['meta' => ['payment_type' => 'offline']]);
    expect($exporter->sendOrder($order))->toBeTrue();
});

it('generateInvoice returns empty array and downloadInvoicePDF returns null (stubbed)', function () {
    $exporter = new MagisterErpExporter(\Mockery::mock(MagisterApiClient::class));
    $user = $this->createUser();
    $country = Country::factory()->create();
    $customer = Customer::factory()->create();
    $group = CustomerGroup::where('default', true)->first();
    $customer->customerGroups()->attach($group->id);
    $customer->users()->attach($user->id);
    $order = Order::factory()
        ->for($customer)
        ->for($user)
        ->has(OrderAddress::factory()->state([
            'type' => 'billing',
            'contact_email' => 'b@example.com',
            'line_one' => 'L1',
            'city' => 'Cluj',
            'state' => 'Cluj',
            'country_id' => $country->id,
        ]), 'billingAddress')
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'first_name' => 'J',
            'last_name' => 'D',
            'city' => 'Arad',
            'postcode' => '310000',
            'contact_phone' => '+407',
            'contact_email' => 's@example.com',
            'line_one' => 'S1',
            'country_id' => $country->id,
        ]), 'shippingAddress')
        ->create(['meta' => ['payment_type' => 'offline']]);
    expect($exporter->generateInvoice($order))->toBe([])
        ->and($exporter->downloadInvoicePDF($order))->toBeNull();
});
