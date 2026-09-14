<?php

uses(\Lunar\Tests\Mailchimp\TestCase::class);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Jobs\SyncSubscriberToMailchimp;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;
use Lunar\Models\Customer;

beforeEach(function () {
    Queue::fake();

    Config::set('lunar.mailchimp.enabled', true);
    Config::set('lunar.mailchimp.api_key', 'test-api-key');
    Config::set('lunar.mailchimp.list_id', 'test-list-id');
    Config::set('lunar.mailchimp.store_id', 'test-store-id');
    Config::set('lunar.mailchimp.server', 'us1');
    Config::set('lunar.mailchimp.retry.max_attempts', 4);
    Config::set('lunar.mailchimp.retry.backoff', [60, 300, 3600]);
});

test('job can be dispatched successfully', function () {
    Queue::assertNothingPushed();

    $customer = Customer::factory()->create();

    SyncSubscriberToMailchimp::dispatch($customer);

    Queue::assertPushed(SyncSubscriberToMailchimp::class);
});

test('job syncs subscriber to Mailchimp', function () {
    $customer = Customer::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $job = new SyncSubscriberToMailchimp($customer);

    // Mock the subscriber service
    $mockService = Mockery::mock(MailchimpSubscriberService::class);
    $mockService->shouldReceive('syncSubscriber')
        ->once()
        ->with($customer, [])
        ->andReturn(['email_address' => 'test@example.com', 'status' => 'subscribed']);

    $this->app->instance(MailchimpSubscriberService::class, $mockService);
    $job->handle();

    expect(true)->toBeTrue(); // No exception thrown
});

test('job does not run when mailchimp is disabled', function () {
    Config::set('lunar.mailchimp.enabled', false);

    $customer = Customer::factory()->create();

    $job = new SyncSubscriberToMailchimp($customer);

    // Should return early without calling service
    $job->handle();

    expect(true)->toBeTrue();
});

test('job throws FailedMailchimpSyncException on API failure', function () {
    $customer = Customer::factory()->create();

    // Mock the subscriber service to throw exception
    $mockService = Mockery::mock(MailchimpSubscriberService::class);
    $mockService->shouldReceive('syncSubscriber')
        ->once()
        ->with($customer, [])
        ->andThrow(new \Exception('Failed to sync subscriber'));

    $job = new SyncSubscriberToMailchimp($customer);
    $this->app->instance(MailchimpSubscriberService::class, $mockService);
    $job->handle();
})->throws(FailedMailchimpSyncException::class);

test('job syncs language only when languageOnly flag is set', function () {
    $customer = Customer::factory()->create();

    $mockService = Mockery::mock(MailchimpSubscriberService::class);
    $mockService->shouldReceive('syncSubscriberLanguage')
        ->once()
        ->with($customer)
        ->andReturn([
            'email_address' => 'test@example.com',
            'merge_fields' => ['LANGUAGE' => 'hu'],
        ]);
    $mockService->shouldNotReceive('syncSubscriber');

    $job = new SyncSubscriberToMailchimp($customer, languageOnly: true);
    $this->app->instance(MailchimpSubscriberService::class, $mockService);
    $job->handle();

    expect(true)->toBeTrue();
});

test('job includes merge fields when provided', function () {
    $customer = Customer::factory()->create();

    $mergeFields = ['CUSTOM' => 'value'];

    // Mock the subscriber service
    $mockService = Mockery::mock(MailchimpSubscriberService::class);
    $mockService->shouldReceive('syncSubscriber')
        ->once()
        ->with($customer, $mergeFields)
        ->andReturn([
            'email_address' => 'test@example.com',
            'status' => 'subscribed',
            'merge_fields' => ['CUSTOM' => 'value'],
        ]);

    $job = new SyncSubscriberToMailchimp($customer, $mergeFields);
    $this->app->instance(MailchimpSubscriberService::class, $mockService);
    $job->handle();

    expect(true)->toBeTrue();
});

test('job has correct retry configuration', function () {
    $customer = Customer::factory()->create();

    $job = new SyncSubscriberToMailchimp($customer);

    expect($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([60, 300, 3600]);
});
