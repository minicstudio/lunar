<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\Models\Country;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Lunar\ERP\Contracts\ErpApiClientInterface;
use Lunar\ERP\Exceptions\FailedErpInvoiceGenerationException;
use Lunar\ERP\Providers\Smartbill\Requests\DownloadInvoicePDFRequest;
use Lunar\ERP\Providers\Smartbill\Requests\GenerateInvoiceRequest;
use Lunar\ERP\Providers\Smartbill\SmartbillApiClient;
use Lunar\ERP\Providers\Smartbill\SmartbillErpExporter;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Config::set('lunar.erp.smartbill.base_url', 'https://smartbill.test');
    Config::set('lunar.erp.smartbill.email', 'user@test');
    Config::set('lunar.erp.smartbill.token', 'tok');
    Config::set('lunar.erp.smartbill.company_vat_code', 'RO123');
    Config::set('lunar.erp.smartbill.series_name', 'S');
    Config::set('lunar.erp.smartbill.measuring_unit_name', 'buc');
    Config::set('lunar.erp.smartbill.save_to_db', false);
    Config::set('lunar.erp.smartbill.tax_names', [
        '0' => 'Fara TVA',
        '19' => 'TVA 19%',
        '21' => 'TVA 21%',
    ]);

    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();

    $taxClass = TaxClass::factory()->create(['default' => true]);
    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    TaxRateAmount::factory()->create([
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
        'percentage' => 21,
    ]);
});

function makeOrderForSmartbillExporter(): Order
{
    $user = test()->createUser();
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
            'contact_email' => 'billing@example.com',
            'line_one' => 'Billing Street 1',
            'city' => 'Cluj-Napoca',
            'tax_identifier' => '',
            'state' => 'Cluj',
            'country_id' => $country->id,
        ]), 'billingAddress')
        ->has(OrderAddress::factory()->state([
            'type' => 'shipping',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'city' => 'Arad',
            'postcode' => '310000',
            'contact_phone' => '+40700000000',
            'contact_email' => 'john@example.com',
            'line_one' => 'Street 1',
            'country_id' => $country->id,
        ]), 'shippingAddress')
        ->create([
            'meta' => [
                'payment_type' => 'offline',
            ],
        ]);

    return $order;
}

it('generateInvoice returns series & number and exporter maps order to payload', function () {
    $mock = new MockClient([
        GenerateInvoiceRequest::class => MockResponse::make(['series' => 'S', 'number' => 321], 200),
    ]);

    $client = new SmartbillApiClient;
    $client->withMockClient($mock);

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    $resp = $exporter->generateInvoice($order);
    expect($resp)->toBe(['series' => 'S', 'number' => 321]);
});

it('generateInvoice throws FailedErpInvoiceGenerationException when Smartbill returns error', function () {
    $mock = new MockClient([
        GenerateInvoiceRequest::class => MockResponse::make(['errorText' => 'Invalid'], 200),
    ]);

    $client = new SmartbillApiClient;
    $client->withMockClient($mock);
    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    expect(fn() => $exporter->generateInvoice($order))
        ->toThrow(FailedErpInvoiceGenerationException::class);
});

it('generateInvoice rethrows when client throws with a helpful message', function () {
    $client = \Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andThrow(new RuntimeException('failed'));

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    expect(fn() => $exporter->generateInvoice($order))
        ->toThrow(FailedErpInvoiceGenerationException::class, 'Invoice generation failed: failed');
});

it('downloadInvoicePDF proxies to client', function () {
    $mock = new MockClient([
        DownloadInvoicePDFRequest::class => MockResponse::make('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
    ]);
    $client = new SmartbillApiClient;
    $client->withMockClient($mock);

    $order = makeOrderForSmartbillExporter();
    $meta = $order->meta ?? [];
    $meta['billing_series'] = 'S';
    $meta['billing_number'] = 42;
    $order->meta = $meta;
    $order->save();

    $exporter = new SmartbillErpExporter($client);
    $resp = $exporter->downloadInvoicePDF($order);
    expect($resp->successful())->toBeTrue();
});

it('adds a shipping product when shipping_total > 0 and maps client fields', function () {
    $captured = null;
    $client = \Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 1];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    $order->update(['shipping_total' => 1500]);
    $order->refresh();

    $resp = $exporter->generateInvoice($order);
    expect($resp)->toBe(['series' => 'S', 'number' => 1]);

    $payload = $captured;
    expect($payload)->not->toBeNull();
    expect($payload['client']['vatCode'])->toBe('-');

    $shipping = collect($payload['products'])->firstWhere('code', 'SHIPPING');
    expect($shipping)->not->toBeNull();
    expect($shipping['name'])->toBe('Cost de livrare');
    expect($shipping['isService'])->toBeTrue();
    expect($shipping['measuringUnitName'])->toBe('buc');
});

it('does not add a shipping product when shipping_total is zero', function () {
    $captured = null;
    $client = \Mockery::mock(ErpApiClientInterface::class);
    $client->shouldReceive('generateInvoice')->once()->andReturnUsing(function ($payload) use (&$captured) {
        $captured = $payload->toArray();

        return ['series' => 'S', 'number' => 2];
    });

    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();

    $order->update(['shipping_total' => 0]);
    $order->refresh();

    $resp = $exporter->generateInvoice($order);
    expect($resp)->toBe(['series' => 'S', 'number' => 2]);

    $payload = $captured;
    $shipping = collect($payload['products'])->firstWhere('code', 'SHIPPING');
    expect($shipping)->toBeNull();
});

it('sendOrder returns false by default for Smartbill exporter', function () {
    $client = new SmartbillApiClient;
    $exporter = new SmartbillErpExporter($client);
    $order = makeOrderForSmartbillExporter();
    expect($exporter->sendOrder($order))->toBeFalse();
});
