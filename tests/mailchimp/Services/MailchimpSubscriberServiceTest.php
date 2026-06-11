<?php

use Illuminate\Support\Facades\Config;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Requests\CreateMergeFieldRequest;
use Lunar\Mailchimp\Requests\DeleteMergeFieldRequest;
use Lunar\Mailchimp\Requests\ListMergeFieldsRequest;
use Lunar\Mailchimp\Requests\SyncSubscriberRequest;
use Lunar\Mailchimp\Requests\TrackEventRequest;
use Lunar\Mailchimp\Services\MailchimpService;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;
use Lunar\Models\Customer;
use Lunar\Tests\Core\Stubs\User;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

beforeEach(function () {
    Config::set('lunar.mailchimp.api_key', 'test-api-key');
    Config::set('lunar.mailchimp.list_id', 'test-list-id');
    Config::set('lunar.mailchimp.store_id', 'test-store-id');
    Config::set('lunar.mailchimp.server', 'us1');

    Config::set('lunar.mailchimp.merge_fields.first_name', 'FNAME');
    Config::set('lunar.mailchimp.merge_fields.last_name', 'LNAME');
    Config::set('lunar.mailchimp.merge_fields.preferred_category', 'PREFCAT');
    Config::set('lunar.mailchimp.merge_fields.preferred_subcategory', 'PREFSUBCAT');
    Config::set('lunar.mailchimp.merge_fields.language', 'LANGUAGE');

    $this->mailchimpService = new MailchimpService;
    $this->subscriberService = new MailchimpSubscriberService($this->mailchimpService);
});

test('subscribe creates new subscriber with pending status', function () {
    $mockResponse = MockResponse::make([
        'id' => 'subscriber-123',
        'email_address' => 'test@example.com',
        'status' => 'pending',
    ], 200);

    $mockClient = new MockClient([
        SyncSubscriberRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->subscriberService->subscribe('test@example.com');

    expect($result)
        ->toBeArray()
        ->toHaveKey('email_address', 'test@example.com')
        ->toHaveKey('status', 'pending');
});

test('subscribe handles resubscription for unsubscribed member', function () {
    // Queue responses in order they'll be requested
    $mockClient = new MockClient([
        MockResponse::make([
            'id' => 'subscriber-123',
            'email_address' => 'test@example.com',
            'status' => 'unsubscribed',
        ], 200),
        MockResponse::make([
            'id' => 'subscriber-123',
            'email_address' => 'test@example.com',
            'status' => 'pending',
        ], 200),
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->subscriberService->subscribe('test@example.com');

    expect($result)
        ->toBeArray()
        ->toHaveKey('status', 'pending');
});

test('subscribe handles cleaned member status', function () {
    $mockClient = new MockClient([
        MockResponse::make([
            'email_address' => 'test@example.com',
            'status' => 'cleaned',
        ], 200),
        MockResponse::make([
            'email_address' => 'test@example.com',
            'status' => 'pending',
        ], 200),
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->subscriberService->subscribe('test@example.com');

    expect($result)->toHaveKey('status', 'pending');
});

test('subscribe throws exception on failure', function () {
    $mockResponse = MockResponse::make(['error' => 'Failed'], 400);

    $mockClient = new MockClient([
        SyncSubscriberRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $this->subscriberService->subscribe('test@example.com');
})->throws(FailedMailchimpSyncException::class, 'Failed to subscribe');

test('syncSubscriber delegates to syncSubscriberByEmail', function () {
    $mockResponse = MockResponse::make([
        'email_address' => 'user@example.com',
        'status' => 'subscribed',
    ], 200);

    $mockClient = new MockClient([
        SyncSubscriberRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $result = $this->subscriberService->syncSubscriber($user);

    expect($result)
        ->toBeArray()
        ->toHaveKey('email_address', 'user@example.com');
});

test('getLanguageMergeFields returns language merge field tag and locale', function () {
    $result = $this->subscriberService->getLanguageMergeFields('fr');

    expect($result)->toBe(['LANGUAGE' => 'fr']);
});

test('getLanguageMergeFields returns empty array when locale is missing', function () {
    expect($this->subscriberService->getLanguageMergeFields(null))->toBe([])
        ->and($this->subscriberService->getLanguageMergeFields(''))->toBe([]);
});

test('getCustomerMergeFields extracts locale from linked user', function () {
    $user = User::factory()->make(['locale' => 'de']);
    $customer = Customer::factory()->make();
    $customer->setRelation('users', collect([$user]));

    expect($this->subscriberService->getCustomerMergeFields($customer))->toBe(['LANGUAGE' => 'de']);
});

test('syncSubscriberLanguage returns null when customer has no locale', function () {
    $user = User::factory()->make(['locale' => null]);
    $customer = Customer::factory()->make();
    $customer->setRelation('users', collect([$user]));

    expect($this->subscriberService->syncSubscriberLanguage($customer))->toBeNull();
});

test('syncSubscriberLanguage syncs only the language merge field', function () {
    $mockResponse = MockResponse::make([
        'email_address' => 'test@example.com',
        'merge_fields' => [
            'LANGUAGE' => 'hu',
        ],
    ], 200);

    $mockClient = new MockClient([
        SyncSubscriberRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $user = User::factory()->make([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'locale' => 'hu',
    ]);
    $customer = Customer::factory()->make();
    $customer->setRelation('users', collect([$user]));

    $result = $this->subscriberService->syncSubscriberLanguage($customer);

    expect($result)
        ->toBeArray()
        ->and($result['merge_fields'])->toHaveKey('LANGUAGE', 'hu');
});

test('syncSubscriberByEmail syncs subscriber with merge fields', function () {
    $mockResponse = MockResponse::make([
        'email_address' => 'test@example.com',
        'status' => 'subscribed',
        'merge_fields' => [
            'FNAME' => 'John',
            'LNAME' => 'Doe',
            'PREFCAT' => 'Electronics',
        ],
    ], 200);

    $mockClient = new MockClient([
        SyncSubscriberRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->subscriberService->syncSubscriberByEmail(
        'test@example.com',
        'John',
        'Doe',
        ['PREFCAT' => 'Electronics']
    );

    expect($result)
        ->toBeArray()
        ->toHaveKey('email_address', 'test@example.com')
        ->and($result['merge_fields'])->toHaveKey('PREFCAT', 'Electronics');
});

test('syncSubscriberByEmail throws exception on failure', function () {
    $mockResponse = MockResponse::make(['error' => 'Failed'], 400);

    $mockClient = new MockClient([
        SyncSubscriberRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $this->subscriberService->syncSubscriberByEmail('test@example.com', 'John', 'Doe');
})->throws(FailedMailchimpSyncException::class, 'Failed to sync subscriber');

test('trackEvent tracks custom event for subscriber', function () {
    $mockResponse = MockResponse::make([
        'name' => 'add_to_cart',
        'properties' => ['product_id' => '123'],
    ], 200);

    $mockClient = new MockClient([
        TrackEventRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->subscriberService->trackEvent(
        'test@example.com',
        'add_to_cart',
        ['product_id' => '123', 'price' => '99.99']
    );

    expect($result)
        ->toBeArray()
        ->toHaveKey('name', 'add_to_cart');
});

test('trackEvent throws exception on failure', function () {
    $mockResponse = MockResponse::make(['error' => 'Failed'], 400);

    $mockClient = new MockClient([
        TrackEventRequest::class => $mockResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $this->subscriberService->trackEvent('test@example.com', 'test_event');
})->throws(FailedMailchimpSyncException::class, 'Failed to track event');

test('setupMergeFields creates merge fields', function () {
    $mockListResponse = MockResponse::make([
        'merge_fields' => [],
    ], 200);

    $mockCreateResponse = MockResponse::make([
        'merge_id' => 1,
        'tag' => 'PREFCAT',
        'name' => 'Preferred Category',
    ], 200);

    $mockClient = new MockClient([
        ListMergeFieldsRequest::class => $mockListResponse,
        CreateMergeFieldRequest::class => $mockCreateResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->subscriberService->setupMergeFields();

    expect($result)
        ->toBeArray()
        ->toHaveKey('PREFCAT')
        ->and($result['PREFCAT']['success'])->toBeTrue();
});

test('setupMergeFields updates existing merge fields', function () {
    // Queue responses for: 1 list request + 2 update requests
    $mockClient = new MockClient([
        MockResponse::make([
            'merge_fields' => [
                ['merge_id' => 1, 'tag' => 'PREFCAT', 'name' => 'Old Name'],
                ['merge_id' => 2, 'tag' => 'PREFSUBCAT', 'name' => 'Old Subcategory'],
            ],
        ], 200),
        MockResponse::make([
            'merge_id' => 1,
            'tag' => 'PREFCAT',
            'name' => 'Preferred Category',
        ], 200),
        MockResponse::make([
            'merge_id' => 2,
            'tag' => 'PREFSUBCAT',
            'name' => 'Preferred Subcategory',
        ], 200),
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->subscriberService->setupMergeFields();

    expect($result)
        ->toBeArray()
        ->toHaveKey('PREFCAT')
        ->and($result['PREFCAT']['success'])->toBeTrue();
});

test('deleteMergeFields deletes existing merge fields', function () {
    $mockListResponse = MockResponse::make([
        'merge_fields' => [
            ['merge_id' => 1, 'tag' => 'OLDFIELD', 'name' => 'Old Field'],
        ],
    ], 200);

    $mockDeleteResponse = MockResponse::make([], 204);

    $mockClient = new MockClient([
        ListMergeFieldsRequest::class => $mockListResponse,
        DeleteMergeFieldRequest::class => $mockDeleteResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->subscriberService->deleteMergeFields(['OLDFIELD' => 'Old Field']);

    expect($result)
        ->toBeArray()
        ->toHaveKey('OLDFIELD')
        ->and($result['OLDFIELD']['success'])->toBeTrue()
        ->and($result['OLDFIELD']['data']['deleted'])->toBeTrue();
});

test('deleteMergeFields handles non-existent fields', function () {
    $mockListResponse = MockResponse::make([
        'merge_fields' => [],
    ], 200);

    $mockClient = new MockClient([
        ListMergeFieldsRequest::class => $mockListResponse,
    ]);

    $this->mailchimpService->getConnector()->withMockClient($mockClient);

    $result = $this->subscriberService->deleteMergeFields(['NOTEXIST' => 'Not Exist']);

    expect($result)
        ->toBeArray()
        ->toHaveKey('NOTEXIST')
        ->and($result['NOTEXIST']['success'])->toBeTrue()
        ->and($result['NOTEXIST']['data']['deleted'])->toBeFalse()
        ->and($result['NOTEXIST']['data']['reason'])->toBe('Field does not exist');
});
