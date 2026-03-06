<?php

uses(\Lunar\Tests\ERP\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\ERP\Exceptions\InvalidErpResponseException;
use Lunar\ERP\Providers\Magister\MagisterApiClient;
use Lunar\ERP\Providers\Magister\Requests\ConfirmReceivingDataRequest;
use Lunar\ERP\Providers\Magister\Requests\GetAttributesRequest;
use Lunar\ERP\Providers\Magister\Requests\GetLocalitiesRequest;
use Lunar\ERP\Providers\Magister\Requests\GetModifiedDeliveryOrderRequest;
use Lunar\ERP\Providers\Magister\Requests\GetModifiedStockByShopRequest;
use Lunar\ERP\Providers\Magister\Requests\GetNextModifiedArticlesRequest;
use Lunar\ERP\Providers\Magister\Requests\SendOrderRequest;
use Lunar\ERP\Providers\Smartbill\DTOs\SmartbillPrintRequestQuery;
use Lunar\Models\Country;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Config::set('lunar.erp.magister.base_url', 'https://magister.test');
    Config::set('lunar.erp.magister.app_id', 'APP');
    Config::set('lunar.erp.magister.shop_id', 1);

    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();
});

function makeOrderForMagisterApi(): Order
{
    $user = test()->createUser();
    $country = Country::factory()->create();
    $customer = Customer::factory()->create();
    $group = CustomerGroup::where('default', true)->first();
    $customer->customerGroups()->attach($group->id);
    $customer->users()->attach($user->id);

    return Order::factory()
        ->for($customer)
        ->for($user)
        ->has(OrderAddress::factory()->state([
            'type' => 'billing',
            'contact_email' => 'billing@example.com',
            'line_one' => 'Billing 1',
            'city' => 'Cluj',
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
}

it('getProductList returns JSON', function () {
    $mock = new MockClient([
        GetNextModifiedArticlesRequest::class => MockResponse::make(['result' => [['DATASET' => [['RECVERSION' => 9]]]]], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $res = $api->getProductList();
    expect($res)->toHaveKey('result');
});

it('getProductList throws on failure', function () {
    $mock = new MockClient([
        GetNextModifiedArticlesRequest::class => MockResponse::make('Error', 500),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);

    expect(fn() => $api->getProductList())
        ->toThrow(InvalidErpResponseException::class);
});

it('confirmReceivingData returns JSON', function () {
    $mock = new MockClient([
        ConfirmReceivingDataRequest::class => MockResponse::make(['ok' => true], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $res = $api->confirmReceivingData(101, 9);
    expect($res)->toBe(['ok' => true]);
});

it('confirmReceivingData throws on failure', function () {
    $mock = new MockClient([
        ConfirmReceivingDataRequest::class => MockResponse::make('Bad', 400),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);

    expect(fn() => $api->confirmReceivingData(101, 9))
        ->toThrow(InvalidErpResponseException::class);
});

it('getStock returns JSON', function () {
    $mock = new MockClient([
        GetModifiedStockByShopRequest::class => MockResponse::make(['result' => [['DATASET' => [['RECVERSION' => 2]]]]], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $res = $api->getStock();
    expect($res)->toHaveKey('result');
});

it('getStock throws on failure', function () {
    $mock = new MockClient([
        GetModifiedStockByShopRequest::class => MockResponse::make('Oops', 500),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);

    expect(fn() => $api->getStock())
        ->toThrow(InvalidErpResponseException::class);
});

it('sendOrder returns JSON', function () {
    $mock = new MockClient([
        SendOrderRequest::class => MockResponse::make(['success' => true], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $order = makeOrderForMagisterApi();
    $res = $api->sendOrder($order);
    expect($res)->toBe(['success' => true]);
});

it('sendOrder throws on failure', function () {
    $mock = new MockClient([
        SendOrderRequest::class => MockResponse::make('Fail', 500),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $order = makeOrderForMagisterApi();

    expect(fn() => $api->sendOrder($order))
        ->toThrow(InvalidErpResponseException::class);
});

it('sendOrder payload contains expected offline fields and customer/company when provided', function () {
    $mock = new MockClient([
        SendOrderRequest::class => MockResponse::make(['success' => true], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);

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
            'first_name' => 'Bill',
            'last_name' => 'Buyer',
            'company_name' => 'ACME Inc',
            'contact_email' => 'billing@example.com',
            'line_one' => 'Billing 1',
            'city' => 'Cluj',
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
            'delivery_instructions' => 'Leave at door',
            'country_id' => $country->id,
        ]), 'shippingAddress')
        ->create([
            'meta' => [
                'payment_type' => 'offline',
            ],
        ]);

    $api->sendOrder($order);

    $mock->assertSent(function ($request, $response) {
        if (! $request instanceof SendOrderRequest) {
            return false;
        }
        $body = $response->getPendingRequest()->body()->all();

        return $body['STATUS'] === 2
            && $body['TYPEOF_DELIVERY'] === 2
            && $body['STATUS_SUBTYPE'] === 22
            && $body['TYPEOF_PAYMENT'] === 1
            && $body['USE_DELIVERY_ADRESS'] === 1
            && $body['ORDER_OBS'] === 'Leave at door'
            && $body['INVOICE_COMPANY_NAME'] === 'ACME Inc'
            && array_key_exists('INVOICE_VAT_NUMBER', $body)
            && isset($body['IDEXTAPP_CUSTOMER']);
    });
});

it('getModifiedOrders returns JSON', function () {
    $mock = new MockClient([
        GetModifiedDeliveryOrderRequest::class => MockResponse::make(['result' => [['DATASET' => []]]], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    $res = $api->getModifiedOrders();
    expect($res)->toHaveKey('result');
});

it('getModifiedOrders throws on failure', function () {
    $mock = new MockClient([
        GetModifiedDeliveryOrderRequest::class => MockResponse::make('Bad', 400),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);

    expect(fn() => $api->getModifiedOrders())
        ->toThrow(InvalidErpResponseException::class);
});

it('getLocalities and getAttributes return JSON', function () {
    $mock = new MockClient([
        GetLocalitiesRequest::class => MockResponse::make(['result' => [['DATASET' => []]]], 200),
        GetAttributesRequest::class => MockResponse::make(['result' => [['DATASET' => []]]], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    expect($api->getLocalities())->toHaveKey('result')
        ->and($api->getAttributes())->toHaveKey('result');
});

it('getLocalities throws on failure', function () {
    $mock = new MockClient([
        GetLocalitiesRequest::class => MockResponse::make('Err', 500),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    expect(fn() => $api->getLocalities())
        ->toThrow(InvalidErpResponseException::class);
});

it('getAttributes throws on failure', function () {
    $mock = new MockClient([
        GetAttributesRequest::class => MockResponse::make('Err', 500),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);
    expect(fn() => $api->getAttributes())
        ->toThrow(InvalidErpResponseException::class);
});

it('sendOrder payload switches to online payment type', function () {
    $mock = new MockClient([
        SendOrderRequest::class => MockResponse::make(['success' => true], 200),
    ]);
    $api = new MagisterApiClient;
    $api->withMockClient($mock);

    $order = makeOrderForMagisterApi();
    $meta = $order->getAttribute('meta');
    $meta['payment_type'] = 'online';
    $order->meta = $meta;
    $order->save();

    $api->sendOrder($order);

    $mock->assertSent(function ($request, $response) {
        if (! $request instanceof SendOrderRequest) {
            return false;
        }
        $body = $response->getPendingRequest()->body()->all();

        return $body['STATUS_SUBTYPE'] === 22 && $body['TYPEOF_PAYMENT'] === 2;
    });
});

it('generateInvoice returns empty array and downloadInvoicePDF returns null', function () {
    $api = new MagisterApiClient;

    $dto = new SmartbillPrintRequestQuery(series: 'S', number: '1', companyVatCode: 'RO123');

    expect($api->generateInvoice($dto))->toBeArray()->toBeEmpty();
    expect($api->downloadInvoicePDF($dto))->toBeNull();
});
