<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Jobs\SyncSubscriberToMailchimp;
use Lunar\Mailchimp\Services\MailchimpSubscriberService;
use Lunar\Tests\Core\Stubs\User;

beforeEach(function () {
    Queue::fake();

    Config::set('lunar-frontend.mailchimp.enabled', true);
    Config::set('lunar-frontend.mailchimp.api_key', 'test-api-key');
    Config::set('lunar-frontend.mailchimp.list_id', 'test-list-id');
    Config::set('lunar-frontend.mailchimp.store_id', 'test-store-id');
    Config::set('lunar-frontend.mailchimp.server', 'us1');
    Config::set('lunar-frontend.mailchimp.retry.max_attempts', 4);
    Config::set('lunar-frontend.mailchimp.retry.backoff', [60, 300, 3600]);
});

test('job can be dispatched successfully', function () {
    Queue::assertNothingPushed();

    $user = User::factory()->create();

    SyncSubscriberToMailchimp::dispatch($user);

    Queue::assertPushed(SyncSubscriberToMailchimp::class);
});

test('job syncs subscriber to Mailchimp', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $job = new SyncSubscriberToMailchimp($user);

    // Mock the subscriber service
    $mockService = Mockery::mock(MailchimpSubscriberService::class);
    $mockService->shouldReceive('syncSubscriber')
        ->once()
        ->with($user, null)
        ->andReturn(['email_address' => $user->email, 'status' => 'subscribed']);

    $job->handle($mockService);

    expect(true)->toBeTrue(); // No exception thrown
});

test('job does not run when mailchimp is disabled', function () {
    Config::set('lunar-frontend.mailchimp.enabled', false);

    $user = User::factory()->create();

    $job = new SyncSubscriberToMailchimp($user);

    // Should return early without calling service
    $job->handle(app(MailchimpSubscriberService::class));

    expect(true)->toBeTrue();
});

test('job throws FailedMailchimpSyncException on API failure', function () {
    $user = User::factory()->create();

    // Mock the subscriber service to throw exception
    $mockService = Mockery::mock(MailchimpSubscriberService::class);
    $mockService->shouldReceive('syncSubscriber')
        ->once()
        ->with($user, null)
        ->andThrow(new \Exception('Failed to sync subscriber'));

    $job = new SyncSubscriberToMailchimp($user);
    $job->handle($mockService);
})->throws(FailedMailchimpSyncException::class);

test('job includes merge fields when provided', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $mergeFields = ['CUSTOM' => 'value'];

    // Mock the subscriber service
    $mockService = Mockery::mock(MailchimpSubscriberService::class);
    $mockService->shouldReceive('syncSubscriber')
        ->once()
        ->with($user, $mergeFields)
        ->andReturn([
            'email_address' => 'test@example.com',
            'status' => 'subscribed',
            'merge_fields' => ['CUSTOM' => 'value'],
        ]);

    $job = new SyncSubscriberToMailchimp($user, $mergeFields);
    $job->handle($mockService);

    expect(true)->toBeTrue();
});

test('job has correct retry configuration', function () {
    $user = User::factory()->create();

    $job = new SyncSubscriberToMailchimp($user);

    expect($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([60, 300, 3600]);
});
