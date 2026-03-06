<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Contracts\ErpDataExporterInterface;
use Lunar\ERP\Contracts\ErpDataImporterInterface;
use Lunar\ERP\Enums\ErpProviderEnum;
use Lunar\ERP\Exceptions\ErpInitializationException;
use Lunar\ERP\Providers\Magister\MagisterApiClient;
use Lunar\ERP\Providers\Magister\MagisterErpProvider;
use Lunar\ERP\Providers\Magister\Requests\GetAttributesRequest;
use Lunar\ERP\Providers\Magister\Requests\GetLocalitiesRequest;
use Lunar\ERP\Providers\Smartbill\Requests\DownloadInvoicePDFRequest;
use Lunar\ERP\Providers\Smartbill\Requests\GenerateInvoiceRequest;
use Lunar\ERP\Providers\Smartbill\SmartbillApiClient;
use Lunar\ERP\Providers\Smartbill\SmartbillErpExporter;
use Lunar\ERP\Providers\Smartbill\SmartbillErpProvider;
use Lunar\ERP\Services\ErpService;
use Lunar\Models\Country;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxRateAmount;
use Lunar\Models\TaxZone;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();

    Config::set('lunar.erp.enabled', true);
    Config::set('lunar.erp.providers', ['magister', 'smartbill']);

    Config::set('lunar.erp.magister.enabled', true);
    Config::set('lunar.erp.magister.provider_class', MagisterErpProvider::class);
    Config::set('lunar.erp.magister.client_class', MagisterApiClient::class);

    Config::set('lunar.erp.smartbill.enabled', true);
    Config::set('lunar.erp.smartbill.client_class', SmartbillApiClient::class);
    Config::set('lunar.erp.smartbill.exporter_class', SmartbillErpExporter::class);
    Config::set('lunar.erp.smartbill.base_url', 'https://smartbill.test');
    Config::set('lunar.erp.smartbill.email', 'user@example.com');
    Config::set('lunar.erp.smartbill.token', 'token');
    Config::set('lunar.erp.smartbill.company_vat_code', 'RO123');
    Config::set('lunar.erp.smartbill.series_name', 'S');
    Config::set('lunar.erp.smartbill.measuring_unit_name', 'buc');
    Config::set('lunar.erp.smartbill.is_service', false);
    Config::set('lunar.erp.smartbill.save_to_db', false);
    Config::set('lunar.erp.smartbill.tax_names', [
        '0' => 'Fara TVA',
        '19' => 'TVA 19%',
        '21' => 'TVA 21%',
    ]);

    $taxClass = TaxClass::factory()->create(['default' => true]);
    $taxZone = TaxZone::factory()->create(['default' => true]);
    $taxRate = TaxRate::factory()->create(['tax_zone_id' => $taxZone->id]);
    TaxRateAmount::factory()->create([
        'tax_class_id' => $taxClass->id,
        'tax_rate_id' => $taxRate->id,
        'percentage' => 19,
    ]);

    Config::set('lunar.erp.sync.products', ['magister']);
    Config::set('lunar.erp.sync.orders', ['magister']);
    Config::set('lunar.erp.sync.stock', ['magister']);
    Config::set('lunar.erp.actions.send_order', ['magister']);
    Config::set('lunar.erp.sync.localities', ['magister']);
    Config::set('lunar.erp.sync.attributes', ['magister']);
    Config::set('lunar.erp.actions.billing', ['smartbill']);
});

function makeOrderForErp(): Order
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
            'state' => 'Cluj',
            'tax_identifier' => '',
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

it('syncProducts returns importer result when allowed', function () {
    Config::set('lunar.erp.magister.importer_class', ErpDataImporterInterface::class);

    $mockImporter = \Mockery::mock(ErpDataImporterInterface::class);
    $mockImporter->shouldReceive('syncProducts')->andReturn(['success' => true, 'source' => 'mock']);
    app()->singleton(ErpDataImporterInterface::class, fn() => $mockImporter);

    $service = new ErpService;
    $res = $service->syncProducts(ErpProviderEnum::magister);
    expect($res['success'])->toBeTrue()->and($res['source'])->toBe('mock');
});

it('syncProducts returns message when not allowed', function () {
    Config::set('lunar.erp.sync.products', []);

    $service = new ErpService;
    $res = $service->syncProducts(ErpProviderEnum::magister);
    expect($res['success'])->toBeFalse()
        ->and($res['message'])->toContain('Product sync is not enabled');
});

it('syncStock returns message when not allowed', function () {
    Config::set('lunar.erp.sync.stock', []);

    $service = new ErpService;
    $res = $service->syncStock(ErpProviderEnum::magister);
    expect($res['success'])->toBeFalse()
        ->and($res['message'])->toContain('Stock sync is not enabled');
});

it('syncStock returns importer result when allowed', function () {
    Config::set('lunar.erp.sync.stock', ['magister']);
    Config::set('lunar.erp.magister.importer_class', ErpDataImporterInterface::class);

    $mockImporter = \Mockery::mock(ErpDataImporterInterface::class);
    $mockImporter->shouldReceive('syncStock')->andReturn(['success' => true, 'synced' => 5]);
    app()->singleton(ErpDataImporterInterface::class, fn() => $mockImporter);

    $service = new ErpService;
    $res = $service->syncStock(ErpProviderEnum::magister, function () {});
    expect($res)->toBe(['success' => true, 'synced' => 5]);
});

it('syncOrderStatuses returns message when not allowed', function () {
    Config::set('lunar.erp.sync.orders', []);

    $service = new ErpService;
    $res = $service->syncOrderStatuses(ErpProviderEnum::magister);
    expect($res['success'])->toBeFalse()
        ->and($res['message'])->toContain('Order sync is not enabled');
});

it('syncOrderStatuses returns importer result when allowed', function () {
    Config::set('lunar.erp.sync.orders', ['magister']);
    Config::set('lunar.erp.magister.importer_class', ErpDataImporterInterface::class);

    $mockImporter = \Mockery::mock(ErpDataImporterInterface::class);
    $mockImporter->shouldReceive('syncOrderStatuses')->andReturn(['success' => true, 'updated' => 3]);
    app()->singleton(ErpDataImporterInterface::class, fn() => $mockImporter);

    $service = new ErpService;
    $res = $service->syncOrderStatuses(ErpProviderEnum::magister);
    expect($res)->toBe(['success' => true, 'updated' => 3]);
});

it('sendOrder respects feature gate and returns false when disabled', function () {
    Config::set('lunar.erp.actions.send_order', []);

    $order = makeOrderForErp();
    $service = new ErpService;
    expect($service->sendOrder(ErpProviderEnum::magister, $order))->toBeFalse();
});

it('sendOrder returns true when enabled', function () {
    Config::set('lunar.erp.actions.send_order', ['magister']);
    Config::set('lunar.erp.magister.exporter_class', ErpDataExporterInterface::class);

    $mockExporter = \Mockery::mock(ErpDataExporterInterface::class);
    $mockExporter->shouldReceive('sendOrder')->andReturnTrue();
    app()->singleton(ErpDataExporterInterface::class, fn() => $mockExporter);

    $order = makeOrderForErp();
    $service = new ErpService;
    expect($service->sendOrder(ErpProviderEnum::magister, $order))->toBeTrue();
});

it('getEnabledProviders returns enabled enums', function () {
    $service = new ErpService;
    $enabled = $service->getEnabledProviders();
    expect($enabled)->toContain(ErpProviderEnum::magister, ErpProviderEnum::smartbill);
});

it('getEnabledProviders returns empty when ERP globally disabled', function () {
    Config::set('lunar.erp.enabled', false);

    $service = new ErpService;
    $enabled = $service->getEnabledProviders();
    expect($enabled)->toBe([]);
});

it('getEnabledProviders excludes providers that are not enabled', function () {
    Config::set('lunar.erp.magister.enabled', false);
    Config::set('lunar.erp.smartbill.enabled', true);

    $service = new ErpService;
    $enabled = $service->getEnabledProviders();
    expect($enabled)->toBe([ErpProviderEnum::smartbill]);
});

it('getAllowedProviders returns only enabled & allowed', function () {
    $service = new ErpService;
    $billing = $service->getAllowedProviders('actions', 'billing');
    $products = $service->getAllowedProviders('sync', 'products');

    expect($billing)->toEqual([ErpProviderEnum::smartbill])
        ->and($products)->toEqual([ErpProviderEnum::magister]);
});

it('getAllowedProviders returns empty when ERP globally disabled', function () {
    Config::set('lunar.erp.enabled', false);

    $service = new ErpService;
    $allowed = $service->getAllowedProviders('sync', 'products');
    expect($allowed)->toBe([]);
});

it('getLocalities returns parsed results from Magister', function () {
    Config::set('lunar.erp.magister.base_url', 'https://magister.test');
    Config::set('lunar.erp.magister.app_id', 'APPID');
    Config::set('lunar.erp.magister.shop_id', 1);

    $mock = new MockClient([
        GetLocalitiesRequest::class => MockResponse::make([
            'result' => [[
                'DATASET' => [
                    ['COUNTY_CODE' => 'CJ', 'COUNTY' => 'Cluj', 'TOWN' => 'Cluj-Napoca'],
                ],
            ]],
        ], 200),
    ]);

    $client = new MagisterApiClient;
    $client->withMockClient($mock);
    app()->singleton(MagisterApiClient::class, fn() => $client);

    app()->singleton(MagisterErpProvider::class, fn() => new MagisterErpProvider($client));

    $service = new ErpService;
    $localities = $service->getLocalities(ErpProviderEnum::magister);
    expect($localities)->toBe([
        ['countyCode' => 'CJ', 'countyName' => 'Cluj', 'localityName' => 'Cluj-Napoca'],
    ]);
});

it('getAttributes returns parsed results from Magister', function () {
    Config::set('lunar.erp.magister.base_url', 'https://magister.test');
    Config::set('lunar.erp.magister.app_id', 'APPID');
    Config::set('lunar.erp.magister.shop_id', 1);
    $mock = new MockClient([
        GetAttributesRequest::class => MockResponse::make([
            'result' => [[
                'DATASET' => [
                    [
                        'NAME' => 'Size',
                        'ITEMS' => [['NAME' => 'S'], ['NAME' => 'M']],
                    ],
                ],
            ]],
        ], 200),
    ]);

    $client = new MagisterApiClient;
    $client->withMockClient($mock);
    app()->singleton(MagisterApiClient::class, fn() => $client);
    app()->singleton(MagisterErpProvider::class, fn() => new MagisterErpProvider($client));

    $service = new ErpService;
    $attributes = $service->getAttributes(ErpProviderEnum::magister);
    expect($attributes)->toBe([
        ['optionName' => 'Size', 'optionValues' => ['S', 'M']],
    ]);
});

it('getLocalities returns empty array when action is not allowed', function () {
    Config::set('lunar.erp.sync.localities', []);

    $service = new ErpService;
    $localities = $service->getLocalities(ErpProviderEnum::magister);
    expect($localities)->toBe([]);
});

it('getLocalities returns empty array when provider does not support SupportsLocalities', function () {
    Config::set('lunar.erp.sync.localities', ['smartbill']);
    Config::set('lunar.erp.smartbill.provider_class', SmartbillErpProvider::class);

    app()->singleton(SmartbillErpProvider::class, fn() => new SmartbillErpProvider(new SmartbillApiClient));

    $service = new ErpService;
    $localities = $service->getLocalities(ErpProviderEnum::smartbill);
    expect($localities)->toBe([]);
});

it('getAttributes returns empty array when action is not allowed', function () {
    Config::set('lunar.erp.sync.attributes', []);

    $service = new ErpService;
    $attributes = $service->getAttributes(ErpProviderEnum::magister);
    expect($attributes)->toBe([]);
});

it('generateInvoice does nothing when billing action is not allowed', function () {
    Config::set('lunar.erp.actions.billing', []);

    $order = makeOrderForErp();
    $originalMeta = $order->meta;

    $service = new ErpService;
    $service->generateInvoice(ErpProviderEnum::smartbill, $order);

    $order->refresh();
    expect($order->meta)->toEqual($originalMeta);
});

it('generateInvoice stores series and number on order meta', function () {
    $mock = new MockClient([
        GenerateInvoiceRequest::class => MockResponse::make(['series' => 'S', 'number' => 123], 200),
    ]);

    $client = new SmartbillApiClient;
    $client->withMockClient($mock);
    app()->singleton(SmartbillApiClient::class, fn() => $client);

    $order = makeOrderForErp();
    $service = new ErpService;
    $service->generateInvoice(ErpProviderEnum::smartbill, $order);

    $order->refresh();
    expect($order->meta['billing_series'] ?? null)->toBe('S')
        ->and($order->meta['billing_number'] ?? null)->toBe(123);
});

it('downloadInvoicePDF proxies to provider and returns response', function () {
    $mock = new MockClient([
        DownloadInvoicePDFRequest::class => MockResponse::make('%PDF-1.7 ...', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $client = new SmartbillApiClient;
    $client->withMockClient($mock);
    app()->singleton(SmartbillApiClient::class, fn() => $client);

    $order = makeOrderForErp();
    $meta = $order->meta ?? [];
    $meta['billing_series'] = 'S';
    $meta['billing_number'] = 123;
    $order->meta = $meta;
    $order->save();

    $service = new ErpService;
    $resp = $service->downloadInvoicePDF(ErpProviderEnum::smartbill, $order);
    expect($resp->successful())->toBeTrue();
});

it('downloadInvoicePDF returns null when billing action is not allowed', function () {
    Config::set('lunar.erp.actions.billing', []);

    $order = makeOrderForErp();
    $service = new ErpService;
    $resp = $service->downloadInvoicePDF(ErpProviderEnum::smartbill, $order);
    expect($resp)->toBeNull();
});

it('getImporter throws when ERP is globally disabled', function () {
    Config::set('lunar.erp.enabled', false);
    Config::set('lunar.erp.sync.products', ['magister']);

    $service = new ErpService;
    expect(fn() => $service->syncProducts(ErpProviderEnum::magister))
        ->toThrow(ErpInitializationException::class, 'ERP is globally disabled.');
});

it('getImporter throws when provider is not enabled', function () {
    Config::set('lunar.erp.sync.products', ['magister']);
    Config::set('lunar.erp.magister.enabled', false);

    $service = new ErpService;
    expect(fn() => $service->syncProducts(ErpProviderEnum::magister))
        ->toThrow(ErpInitializationException::class, 'is not enabled');
});

it('getImporter throws when importer_class is missing', function () {
    Config::set('lunar.erp.sync.products', ['magister']);
    Config::set('lunar.erp.magister.importer_class', null);

    $service = new ErpService;
    expect(fn() => $service->syncProducts(ErpProviderEnum::magister))
        ->toThrow(ErpInitializationException::class, 'does not have an importer class configured');
});

it('getExporter throws when ERP is globally disabled', function () {
    Config::set('lunar.erp.enabled', false);
    Config::set('lunar.erp.actions.send_order', ['magister']);

    $order = makeOrderForErp();
    $service = new ErpService;
    expect(fn() => $service->sendOrder(ErpProviderEnum::magister, $order))
        ->toThrow(ErpInitializationException::class, 'ERP is globally disabled.');
});

it('getExporter throws when provider is not enabled', function () {
    Config::set('lunar.erp.actions.send_order', ['magister']);
    Config::set('lunar.erp.magister.enabled', false);

    $order = makeOrderForErp();
    $service = new ErpService;
    expect(fn() => $service->sendOrder(ErpProviderEnum::magister, $order))
        ->toThrow(ErpInitializationException::class, 'is not enabled');
});

it('getExporter throws when exporter_class is missing', function () {
    Config::set('lunar.erp.actions.send_order', ['magister']);
    Config::set('lunar.erp.magister.exporter_class', null);

    $order = makeOrderForErp();
    $service = new ErpService;
    expect(fn() => $service->sendOrder(ErpProviderEnum::magister, $order))
        ->toThrow(ErpInitializationException::class, 'does not have an exporter class configured');
});
