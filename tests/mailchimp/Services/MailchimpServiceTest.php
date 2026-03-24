<?php

use Illuminate\Support\Facades\Config;
use Lunar\Mailchimp\Connectors\MailchimpConnector;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Exceptions\MissingMailchimpConfigurationException;
use Lunar\Mailchimp\Requests\CreateStoreRequest;
use Lunar\Mailchimp\Requests\GetStoreRequest;
use Lunar\Mailchimp\Services\MailchimpService;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Config::set('lunar-frontend.mailchimp.api_key', 'test-api-key');
    Config::set('lunar-frontend.mailchimp.list_id', 'test-list-id');
    Config::set('lunar-frontend.mailchimp.store_id', 'test-store-id');
    Config::set('lunar-frontend.mailchimp.server', 'us1');
});

test('throws exception when api_key is missing', function () {
    Config::set('lunar-frontend.mailchimp.api_key', '');

    expect(fn () => new MailchimpService)
        ->toThrow(MissingMailchimpConfigurationException::class, 'Missing Mailchimp configuration');
});

test('throws exception when list_id is missing', function () {
    Config::set('lunar-frontend.mailchimp.list_id', '');

    expect(fn () => new MailchimpService)
        ->toThrow(MissingMailchimpConfigurationException::class, 'Missing Mailchimp configuration');
});

test('can instantiate service with valid configuration', function () {
    $service = new MailchimpService();

    expect($service)
        ->toBeInstanceOf(MailchimpService::class)
        ->and($service->getListId())->toBe('test-list-id')
        ->and($service->getStoreId())->toBe('test-store-id');
});

test('getConnector returns MailchimpConnector instance', function () {
    $service = new MailchimpService();

    expect($service->getConnector())
        ->toBeInstanceOf(MailchimpConnector::class);
});

test('createStore creates a new Mailchimp store', function () {
    $mockResponse = MockResponse::make([
        'id' => 'test-store',
        'list_id' => 'test-list-id',
        'name' => 'Test Store',
        'domain' => 'test.com',
    ], 200);

    $mockClient = new MockClient([
        CreateStoreRequest::class => $mockResponse,
    ]);

    $service = new MailchimpService();
    $service->getConnector()->withMockClient($mockClient);

    $result = $service->createStore(
        'test-store',
        'Test Store',
        'test.com',
        'test@test.com',
        'USD'
    );

    expect($result)
        ->toBeArray()
        ->toHaveKey('id', 'test-store')
        ->toHaveKey('name', 'Test Store');
});

test('createStore throws exception on failure', function () {
    $mockResponse = MockResponse::make([
        'type' => 'error',
        'title' => 'Store Creation Failed',
    ], 400);

    $mockClient = new MockClient([
        CreateStoreRequest::class => $mockResponse,
    ]);

    $service = new MailchimpService();
    $service->getConnector()->withMockClient($mockClient);

    $service->createStore(
        'test-store',
        'Test Store',
        'test.com',
        'test@test.com',
        'USD'
    );
})->throws(FailedMailchimpSyncException::class, 'Failed to create store');

test('getStore retrieves a Mailchimp store', function () {
    $mockResponse = MockResponse::make([
        'id' => 'test-store',
        'name' => 'Test Store',
    ], 200);

    $mockClient = new MockClient([
        GetStoreRequest::class => $mockResponse,
    ]);

    $service = new MailchimpService();
    $service->getConnector()->withMockClient($mockClient);

    $result = $service->getStore('test-store');

    expect($result)
        ->toBeArray()
        ->toHaveKey('id', 'test-store');
});

test('getCustomerIdFromEmail generates consistent MD5 hash', function () {
    $service = new MailchimpService();

    $customerId1 = $service->getCustomerIdFromEmail('test@example.com');
    $customerId2 = $service->getCustomerIdFromEmail('TEST@EXAMPLE.COM');
    $customerId3 = $service->getCustomerIdFromEmail('  test@example.com  ');

    expect($customerId1)
        ->toBe($customerId2)
        ->toBe($customerId3)
        ->toBe(md5('test@example.com'));
});

test('ensureStoreIdIsSet throws exception when store_id is empty', function () {
    Config::set('lunar-frontend.mailchimp.store_id', '');

    $service = new MailchimpService();

    $service->ensureStoreIdIsSet();
})->throws(MissingMailchimpConfigurationException::class, 'Store ID is required');

test('ensureStoreIdIsSet does not throw when store_id is set', function () {
    $service = new MailchimpService();

    expect(fn () => $service->ensureStoreIdIsSet())->not->toThrow(MissingMailchimpConfigurationException::class);
});
