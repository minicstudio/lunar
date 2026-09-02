<?php

uses(\Lunar\Tests\Core\TestCase::class);
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Lunar\Events\DiscountUpdatedEvent;
use Lunar\Klaviyo\Jobs\SyncAllProductsToKlaviyo;
use Lunar\Klaviyo\Jobs\SyncProductsBulkToKlaviyo;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountBecameGlobal;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountBecameLimited;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountDeleted;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountLimitationChanged;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountUpdated;
use Lunar\Models\Brand;
use Lunar\Models\Collection;
use Lunar\Models\Currency;
use Lunar\Models\Discount;
use Lunar\Models\Language;
use Lunar\Models\Product;

beforeEach(function () {
    Language::factory()->create(['default' => true, 'code' => 'en']);
    Currency::factory()->create(['default' => true, 'code' => 'EUR']);

    Queue::fake();

    Config::set('lunar.klaviyo.enabled', true);
    Config::set('lunar.klaviyo.api_key', 'pk_test');
    Config::set('lunar.klaviyo.sync_products', true);
    Config::set('lunar.klaviyo.queue_connection', 'deferred');
});

test('discount updated syncs limited product ids', function () {
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $discount = Discount::factory()->create(['name' => 'Summer Sale']);

    $discount->discountables()->create([
        'discountable_type' => Product::morphName(),
        'discountable_id' => $product1->id,
        'type' => 'limitation',
    ]);
    $discount->discountables()->create([
        'discountable_type' => Product::morphName(),
        'discountable_id' => $product2->id,
        'type' => 'limitation',
    ]);

    $discount->name = 'Winter Sale';
    $discount->save();

    (new SyncProductsOnDiscountUpdated)->handle(new DiscountUpdatedEvent($discount));

    Queue::assertPushed(SyncProductsBulkToKlaviyo::class, 1);
    Queue::assertPushed(SyncProductsBulkToKlaviyo::class, function (SyncProductsBulkToKlaviyo $job) use ($product1, $product2) {
        return in_array($product1->id, $job->productIds, true)
            && in_array($product2->id, $job->productIds, true)
            && $job->connection !== 'deferred';
    });
    Queue::assertNotPushed(SyncAllProductsToKlaviyo::class);
});

test('discount updated dispatches full sync when discount is global', function () {
    $discount = Discount::factory()->create(['name' => 'Sitewide']);

    $discount->name = 'Sitewide 20%';
    $discount->save();

    (new SyncProductsOnDiscountUpdated)->handle(new DiscountUpdatedEvent($discount));

    Queue::assertPushed(SyncAllProductsToKlaviyo::class, function (SyncAllProductsToKlaviyo $job) {
        return $job->connection !== 'deferred';
    });
    Queue::assertNotPushed(SyncProductsBulkToKlaviyo::class);
});

test('discount updated no-ops for coupon discounts', function () {
    $product = Product::factory()->create();
    $discount = Discount::factory()->create([
        'name' => 'Coupon Sale',
        'coupon' => 'SAVE10',
    ]);
    $discount->discountables()->create([
        'discountable_type' => Product::morphName(),
        'discountable_id' => $product->id,
        'type' => 'limitation',
    ]);

    $discount->name = 'Coupon Sale Updated';
    $discount->save();

    (new SyncProductsOnDiscountUpdated)->handle(new DiscountUpdatedEvent($discount));

    Queue::assertNothingPushed();
});

test('discount updated no-ops when only uses changes', function () {
    $product = Product::factory()->create();
    $discount = Discount::factory()->create(['uses' => 0]);
    $discount->discountables()->create([
        'discountable_type' => Product::morphName(),
        'discountable_id' => $product->id,
        'type' => 'limitation',
    ]);

    $discount->uses = 1;
    $discount->save();

    (new SyncProductsOnDiscountUpdated)->handle(new DiscountUpdatedEvent($discount));

    Queue::assertNothingPushed();
});

test('discount limitation attached syncs the product', function () {
    $product = Product::factory()->create();
    $discount = Discount::factory()->create();

    $event = new \Lunar\Admin\Events\DiscountLimitationAttached($discount, [
        'discount_id' => $discount->id,
        'discountable_id' => $product->id,
        'discountable_type' => Product::class,
    ]);

    (new SyncProductsOnDiscountLimitationChanged)->handle($event);

    Queue::assertPushed(SyncProductsBulkToKlaviyo::class, function (SyncProductsBulkToKlaviyo $job) use ($product) {
        return in_array($product->id, $job->productIds, true);
    });
});

test('discount limitation attached syncs collection products', function () {
    $collection = Collection::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $product1->collections()->attach($collection);
    $product2->collections()->attach($collection);
    $discount = Discount::factory()->create();

    $event = new \Lunar\Admin\Events\DiscountLimitationAttached($discount, [
        'discount_id' => $discount->id,
        'discountable_id' => $collection->id,
        'discountable_type' => Collection::class,
    ]);

    (new SyncProductsOnDiscountLimitationChanged)->handle($event);

    Queue::assertPushed(SyncProductsBulkToKlaviyo::class, 1);
    Queue::assertPushed(SyncProductsBulkToKlaviyo::class, function (SyncProductsBulkToKlaviyo $job) use ($product1, $product2) {
        return in_array($product1->id, $job->productIds, true)
            && in_array($product2->id, $job->productIds, true);
    });
});

test('discount limitation attached syncs brand products', function () {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);
    $discount = Discount::factory()->create();

    $event = new \Lunar\Admin\Events\DiscountLimitationAttached($discount, [
        'discount_id' => $discount->id,
        'discountable_id' => $brand->id,
        'discountable_type' => Brand::class,
    ]);

    (new SyncProductsOnDiscountLimitationChanged)->handle($event);

    Queue::assertPushed(SyncProductsBulkToKlaviyo::class, function (SyncProductsBulkToKlaviyo $job) use ($product) {
        return in_array($product->id, $job->productIds, true);
    });
});

test('discount became limited dispatches full catalog sync', function () {
    $discount = Discount::factory()->create();

    (new SyncProductsOnDiscountBecameLimited)->handle(
        new \Lunar\Admin\Events\BeforeDiscountLimitationAttached($discount)
    );

    Queue::assertPushed(SyncAllProductsToKlaviyo::class);
});

test('discount became global dispatches full catalog sync', function () {
    $discount = Discount::factory()->create();

    (new SyncProductsOnDiscountBecameGlobal)->handle(
        new \Lunar\Admin\Events\DiscountLimitationDetached($discount)
    );

    Queue::assertPushed(SyncAllProductsToKlaviyo::class);
});

test('discount deleted with related products syncs those products', function () {
    $product = Product::factory()->create();

    $event = new \Lunar\Admin\Events\DiscountDeleted([
        'products' => collect([
            [
                'discount_id' => 1,
                'discountable_id' => $product->id,
                'discountable_type' => Discount::class,
            ],
        ]),
        'collections' => collect(),
    ]);

    (new SyncProductsOnDiscountDeleted)->handle($event);

    Queue::assertPushed(SyncProductsBulkToKlaviyo::class, function (SyncProductsBulkToKlaviyo $job) use ($product) {
        return in_array($product->id, $job->productIds, true);
    });
    Queue::assertNotPushed(SyncAllProductsToKlaviyo::class);
});

test('discount deleted with no related items dispatches full catalog sync', function () {
    $event = new \Lunar\Admin\Events\DiscountDeleted([
        'products' => collect(),
        'collections' => collect(),
    ]);

    (new SyncProductsOnDiscountDeleted)->handle($event);

    Queue::assertPushed(SyncAllProductsToKlaviyo::class);
});

test('discount limitation listener no-ops when sync_products disabled', function () {
    Config::set('lunar.klaviyo.sync_products', false);

    $product = Product::factory()->create();
    $discount = Discount::factory()->create();

    (new SyncProductsOnDiscountLimitationChanged)->handle(
        new \Lunar\Admin\Events\DiscountLimitationAttached($discount, [
            'discountable_id' => $product->id,
            'discountable_type' => Product::class,
        ])
    );

    Queue::assertNothingPushed();
});

test('changing ends_at via save dispatches sync through DiscountUpdatedEvent listener', function () {
    $this->app->register(\Lunar\Klaviyo\KlaviyoServiceProvider::class);

    $product = Product::factory()->create();
    $discount = Discount::factory()->create([
        'ends_at' => now()->addDays(10),
    ]);
    $discount->discountables()->create([
        'discountable_type' => Product::morphName(),
        'discountable_id' => $product->id,
        'type' => 'limitation',
    ]);

    Queue::fake();

    $discount->ends_at = now()->subDay();
    $discount->save();

    Queue::assertPushed(SyncProductsBulkToKlaviyo::class, function (SyncProductsBulkToKlaviyo $job) use ($product) {
        return in_array($product->id, $job->productIds, true);
    });
});

test('getChanges includes ends_at when DiscountUpdatedEvent is fired from model save', function () {
    $seen = null;

    \Illuminate\Support\Facades\Event::listen(DiscountUpdatedEvent::class, function (DiscountUpdatedEvent $event) use (&$seen) {
        $seen = $event->discount->getChanges();
    });

    $discount = Discount::factory()->create([
        'ends_at' => now()->addDays(10),
    ]);

    $discount->ends_at = now()->subDay();
    $discount->save();

    expect($seen)->toBeArray()
        ->and(array_key_exists('ends_at', $seen))->toBeTrue();
});
