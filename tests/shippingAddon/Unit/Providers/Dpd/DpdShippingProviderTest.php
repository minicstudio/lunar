<?php

uses(\Lunar\Tests\ShippingAddon\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Lunar\Addons\Shipping\Contracts\AWBRequestBodyInterface;
use Lunar\Addons\Shipping\Contracts\ShippingApiClient;
use Lunar\Addons\Shipping\Exceptions\FailedAWBGenerationException;
use Lunar\Addons\Shipping\Providers\Dpd\DpdApiClient;
use Lunar\Addons\Shipping\Providers\Dpd\DpdShippingProvider;
use Lunar\Addons\Shipping\Providers\Dpd\Requests\DownloadAWBPDF;
use Lunar\Addons\Shipping\Providers\Dpd\Requests\GenerateAWBRequest;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    $this->createCurrencies();
    Config::set('lunar.shipping.enabled', true);
    Config::set('lunar.shipping.providers', ['dpd']);
    Config::set('lunar.shipping.dpd.enabled', true);
    Config::set('lunar.shipping.dpd.base_url', 'https://dpd.test');
    Config::set('lunar.shipping.dpd.username', 'user');
    Config::set('lunar.shipping.dpd.password', 'pass');
    Config::set('lunar.shipping.dpd.service_id', 1);
    Config::set('lunar.shipping.dpd.contents', 'Goods');
    Config::set('lunar.shipping.dpd.package', 'BOX');
    Config::set('lunar.shipping.dpd.paper_size', 'A4');
});

it('DpdShippingProvider throws when response has no id', function () {
    $mock = new MockClient([
        GenerateAWBRequest::class => MockResponse::make(['message' => 'Bad request'], 200),
    ]);

    $client = new DpdApiClient;
    $client->withMockClient($mock);

    $order = new Order;
    $order->meta = ['payment_type' => 'online'];
    $order->setRelation('productLines', collect());
    $order->setRelation('shippingAddress', new OrderAddress([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'city' => 'CJ',
        'postcode' => '400000',
        'contact_phone' => '+40711111111',
        'contact_email' => 'jane@example.com',
        'line_one' => 'Street 2',
    ]));

    $provider = new DpdShippingProvider($client);
    $this->expectException(FailedAWBGenerationException::class);
    $provider->generateAWB($order);
});

it('isEnabled reflects config flag', function () {
    $client = \Mockery::mock(ShippingApiClient::class);
    $provider = new DpdShippingProvider($client);

    Config::set('lunar.shipping.dpd.enabled', true);
    expect($provider->isEnabled())->toBeTrue();

    Config::set('lunar.shipping.dpd.enabled', false);
    expect($provider->isEnabled())->toBeFalse();
});

it('generateAWB returns awbNumber and builds private recipient payload', function () {
    $captured = [];
    $client = \Mockery::mock(ShippingApiClient::class);
    $client->shouldReceive('generateAWB')
        ->once()
        ->with(\Mockery::on(function (AWBRequestBodyInterface $payload) use (&$captured) {
            $captured = $payload->toArray();

            return true;
        }))
        ->andReturn(['id' => 'AWB123']);

    $provider = new DpdShippingProvider($client);

    $order = new Order;
    $order->meta = ['payment_type' => 'online'];
    $order->setRelation('productLines', collect());
    $order->setRelation('shippingAddress', new OrderAddress([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'company_name' => null,
        'city' => 'Arad',
        'postcode' => '310000',
        'contact_phone' => '+40700000000',
        'contact_email' => 'john@example.com',
        'line_one' => 'Street 1',
        'delivery_instructions' => 'Leave at door please',
    ]));

    $resp = $provider->generateAWB($order);
    expect($resp['awbNumber'])->toBe('AWB123');

    expect($captured)->toHaveKeys(['userName', 'password', 'language', 'recipient', 'service', 'content', 'payment', 'shipmentNote'])
        ->and($captured['recipient']['clientName'])->toBe('John Doe')
        ->and($captured['recipient'])->not()->toHaveKey('contactName')
        ->and($captured['recipient']['privatePerson'])->toBeTrue()
        ->and($captured['recipient']['address'])->toMatchArray(['siteName' => 'Arad', 'postCode' => '310000'])
        ->and($captured['service']['additionalServices']['cod']['amount'])->toBe(0.0)
        ->and($captured['content']['parcelsCount'])->toBe(1)
        ->and($captured['content']['totalWeight'])->toBe(1.0)
        ->and($captured['payment'])->toMatchArray(['courierServicePayer' => 'SENDER', 'packagePayer' => 'RECIPIENT']);
});

it('generateAWB builds corporate recipient when company present', function () {
    $captured = [];
    $client = \Mockery::mock(ShippingApiClient::class);
    $client->shouldReceive('generateAWB')
        ->once()
        ->with(\Mockery::on(function (AWBRequestBodyInterface $payload) use (&$captured) {
            $captured = $payload->toArray();

            return true;
        }))
        ->andReturn(['id' => 'AWB999']);

    $provider = new DpdShippingProvider($client);

    $order = new Order;
    $order->meta = ['payment_type' => 'online'];
    $order->setRelation('productLines', collect());
    $order->setRelation('shippingAddress', new OrderAddress([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'company_name' => 'ACME SRL',
        'city' => 'Cluj',
        'postcode' => '400000',
        'contact_phone' => '+40711111111',
        'contact_email' => 'jane@example.com',
        'line_one' => 'Street 2',
    ]));

    $provider->generateAWB($order);
    $recipient = $captured['recipient'];

    expect($recipient['clientName'])->toBe('ACME SRL')
        ->and($recipient['contactName'])->toBe('Jane Doe')
        ->and($recipient['privatePerson'])->toBeFalse();
});

it('downloadAWBPDF returns successful response', function () {
    $mock = new MockClient([
        DownloadAWBPDF::class => MockResponse::make('%PDF-1.7 ...', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $client = new DpdApiClient;
    $client->withMockClient($mock);

    $provider = new DpdShippingProvider($client);
    $resp = $provider->downloadAWBPDF('AWB123');
    expect($resp->successful())->toBeTrue();
});

it('getCounties, getCities and getLockers return empty collections', function () {
    $client = \Mockery::mock(ShippingApiClient::class);
    $provider = new DpdShippingProvider($client);

    expect($provider->getCounties()->isEmpty())->toBeTrue()
        ->and($provider->getCities(1)->isEmpty())->toBeTrue()
        ->and($provider->getLockers(1, 1)->isEmpty())->toBeTrue();
});

it('generateAWB wraps client exceptions into FailedAWBGenerationException', function () {
    $client = \Mockery::mock(ShippingApiClient::class);
    $client->shouldReceive('generateAWB')
        ->once()
        ->with(\Mockery::type(AWBRequestBodyInterface::class))
        ->andThrow(new Exception('Exception'));

    $provider = new DpdShippingProvider($client);

    $order = new Order;
    $order->meta = ['payment_type' => 'online'];
    $order->setRelation('productLines', collect());
    $order->setRelation('shippingAddress', new OrderAddress([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'city' => 'Arad',
        'postcode' => '310000',
        'contact_phone' => '+40700000000',
        'contact_email' => 'john@example.com',
        'line_one' => 'Street 1',
    ]));

    $this->expectException(FailedAWBGenerationException::class);
    $provider->generateAWB($order);
});
