<?php

uses(\Lunar\Tests\ShippingAddon\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
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

it('DpdApiClient hits correct endpoints with payloads', function () {
    $mock = new MockClient([
        GenerateAWBRequest::class => MockResponse::make(['id' => 'AWB42'], 200),
        DownloadAWBPDF::class => MockResponse::make('%PDF-1.7 ...', 200, ['Content-Type' => 'application/pdf']),
    ]);

    $client = new DpdApiClient;
    $client->withMockClient($mock);

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
        'company_name' => null,
        'delivery_instructions' => 'Leave at door',
    ]));

    $provider = new DpdShippingProvider($client);
    $response = $provider->generateAWB($order);
    expect($response)->toHaveKey('awbNumber')->and($response['awbNumber'])->toBe('AWB42');

    $pdf = $provider->downloadAWBPDF('AWB42');
    expect($pdf->successful())->toBeTrue();
});

it('downloadAWBPDF throws when response is not successful', function () {
    $mock = new MockClient([
        DownloadAWBPDF::class => MockResponse::make('Bad Request', 400, ['Content-Type' => 'text/plain']),
    ]);

    $client = new DpdApiClient;
    $client->withMockClient($mock);

    expect(fn () => $client->downloadAWBPDF('AWB_FAIL'))
        ->toThrow(FailedAWBGenerationException::class);
});
