<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Enums\ProductEventType;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Jobs\SyncProductToMailchimp;
use Lunar\Mailchimp\Services\MailchimpEcommerceService;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

beforeEach(function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();
    $this->createChannel();

    Queue::fake();

    Config::set('lunar-frontend.mailchimp.enabled', true);
    Config::set('lunar-frontend.mailchimp.sync_products', true);
    Config::set('lunar-frontend.mailchimp.api_key', 'test-api-key');
    Config::set('lunar-frontend.mailchimp.list_id', 'test-list-id');
    Config::set('lunar-frontend.mailchimp.store_id', 'test-store-id');
    Config::set('lunar-frontend.mailchimp.server', 'us1');
    Config::set('lunar-frontend.mailchimp.retry.max_attempts', 4);
    Config::set('lunar-frontend.mailchimp.retry.backoff', [60, 300, 3600]);
});

test('job can be dispatched successfully', function () {
    Queue::assertNothingPushed();

    $product = Product::factory()->create();

    SyncProductToMailchimp::dispatch($product);

    Queue::assertPushed(SyncProductToMailchimp::class);
});

test('job syncs product to Mailchimp', function () {
    $currency = Currency::where('default', true)->first();

    $product = Product::factory()->create([
        'status' => 'published',
        'attribute_data' => [
            'name' => new TranslatedText(['en' => 'Test Product']),
        ],
    ]);

    $variant = ProductVariant::factory()->for($product)->create();

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    // Mock the ecommerce service
    $mockService = Mockery::mock(MailchimpEcommerceService::class);
    $mockService->shouldReceive('syncProduct')
        ->once()
        ->with($product)
        ->andReturn(['id' => (string) $product->id, 'title' => 'Test Product']);

    $job = new SyncProductToMailchimp($product, ProductEventType::UPDATE);
    $job->handle($mockService);

    expect(true)->toBeTrue();
});

test('job deletes product when event type is DELETE', function () {
    $product = Product::factory()->create();

    // Mock the ecommerce service
    $mockService = Mockery::mock(MailchimpEcommerceService::class);
    $mockService->shouldReceive('deleteProduct')
        ->once()
        ->with($product)
        ->andReturn(true);

    $job = new SyncProductToMailchimp($product, ProductEventType::DELETE);
    $job->handle($mockService);

    expect(true)->toBeTrue();
});

test('job does not run when mailchimp is disabled', function () {
    Config::set('lunar-frontend.mailchimp.enabled', false);

    $product = Product::factory()->create();

    $job = new SyncProductToMailchimp($product);
    $job->handle(app(MailchimpEcommerceService::class));

    expect(true)->toBeTrue();
});

test('job does not run when sync_products is disabled', function () {
    Config::set('lunar-frontend.mailchimp.sync_products', false);

    $product = Product::factory()->create();

    $job = new SyncProductToMailchimp($product);
    $job->handle(app(MailchimpEcommerceService::class));

    expect(true)->toBeTrue();
});

test('job throws FailedMailchimpSyncException on API failure', function () {
    $currency = Currency::where('default', true)->first();

    $product = Product::factory()->create([
        'status' => 'published',
    ]);

    $variant = ProductVariant::factory()->for($product)->create();

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    // Mock the ecommerce service to throw exception
    $mockService = Mockery::mock(MailchimpEcommerceService::class);
    $mockService->shouldReceive('syncProduct')
        ->once()
        ->with($product)
        ->andThrow(new \Exception('Failed to sync product'));

    $job = new SyncProductToMailchimp($product);
    $job->handle($mockService);
})->throws(FailedMailchimpSyncException::class);

test('job has correct retry configuration', function () {
    $product = Product::factory()->create();

    $job = new SyncProductToMailchimp($product);

    expect($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([60, 300, 3600]);
});
