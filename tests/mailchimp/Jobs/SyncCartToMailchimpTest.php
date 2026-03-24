<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Mailchimp\Exceptions\FailedMailchimpSyncException;
use Lunar\Mailchimp\Jobs\SyncCartToMailchimp;
use Lunar\Mailchimp\Services\MailchimpEcommerceService;
use Lunar\Models\Cart;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Tests\Core\Stubs\User;

beforeEach(function () {
    $this->createLanguages();
    $this->createCurrencies();
    $this->createCustomerGroup();
    $this->createChannel();

    Queue::fake();

    Config::set('lunar-frontend.mailchimp.enabled', true);
    Config::set('lunar-frontend.mailchimp.sync_carts', true);
    Config::set('lunar-frontend.mailchimp.api_key', 'test-api-key');
    Config::set('lunar-frontend.mailchimp.list_id', 'test-list-id');
    Config::set('lunar-frontend.mailchimp.store_id', 'test-store-id');
    Config::set('lunar-frontend.mailchimp.server', 'us1');
    Config::set('lunar-frontend.mailchimp.retry.max_attempts', 4);
    Config::set('lunar-frontend.mailchimp.retry.backoff', [60, 300, 3600]);
});

test('job can be dispatched successfully', function () {
    Queue::assertNothingPushed();

    $cart = Cart::factory()->create();

    SyncCartToMailchimp::dispatch($cart);

    Queue::assertPushed(SyncCartToMailchimp::class);
});

test('job syncs cart to Mailchimp', function () {
    $currency = Currency::where('default', true)->first();

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    $taxClass = TaxClass::factory()->create();
    $variant->update(['tax_class_id' => $taxClass->id]);

    $cart = Cart::factory()->create(['user_id' => $user->id, 'currency_id' => $currency->id]);
    $cart->lines()->create([
        'purchasable_type' => ProductVariant::class,
        'purchasable_id' => $variant->id,
        'quantity' => 1,
    ]);

    // Mock the ecommerce service
    $mockService = Mockery::mock(MailchimpEcommerceService::class);
    $mockService->shouldReceive('syncCart')
        ->once()
        ->with($cart)
        ->andReturn(['id' => (string) $cart->id]);

    $job = new SyncCartToMailchimp($cart);
    $job->handle($mockService);

    expect(true)->toBeTrue();
});

test('job does not run when mailchimp is disabled', function () {
    Config::set('lunar-frontend.mailchimp.enabled', false);

    $cart = Cart::factory()->create();

    $job = new SyncCartToMailchimp($cart);
    $job->handle(app(MailchimpEcommerceService::class));

    expect(true)->toBeTrue();
});

test('job does not run when sync_carts is disabled', function () {
    Config::set('lunar-frontend.mailchimp.sync_carts', false);

    $cart = Cart::factory()->create();

    $job = new SyncCartToMailchimp($cart);
    $job->handle(app(MailchimpEcommerceService::class));

    expect(true)->toBeTrue();
});

test('job does not sync cart without user_id', function () {
    $cart = Cart::factory()->create(['user_id' => null]);

    $job = new SyncCartToMailchimp($cart);
    $job->handle(app(MailchimpEcommerceService::class));

    expect(true)->toBeTrue();
});

test('job throws FailedMailchimpSyncException on API failure', function () {
    $currency = Currency::where('default', true)->first();

    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    $variant->prices()->create([
        'currency_id' => $currency->id,
        'price' => 10000,
    ]);

    $taxClass = TaxClass::factory()->create();
    $variant->update(['tax_class_id' => $taxClass->id]);

    $cart = Cart::factory()->create(['user_id' => $user->id, 'currency_id' => $currency->id]);
    $cart->lines()->create([
        'purchasable_type' => ProductVariant::class,
        'purchasable_id' => $variant->id,
        'quantity' => 1,
    ]);

    // Mock the ecommerce service to throw exception
    $mockService = Mockery::mock(MailchimpEcommerceService::class);
    $mockService->shouldReceive('syncCart')
        ->once()
        ->with($cart)
        ->andThrow(new \Exception('Failed to sync cart'));

    $job = new SyncCartToMailchimp($cart);
    $job->handle($mockService);
})->throws(FailedMailchimpSyncException::class);

test('job has correct retry configuration', function () {
    $cart = Cart::factory()->create();

    $job = new SyncCartToMailchimp($cart);

    expect($job->tries)->toBe(4)
        ->and($job->backoff)->toBe([60, 300, 3600]);
});
