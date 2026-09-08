<?php

namespace Lunar\Tests\ProductFeed;

use Cartalyst\Converter\Laravel\ConverterServiceProvider;
use Illuminate\Support\Facades\Config;
use Kalnoy\Nestedset\NestedSetServiceProvider;
use Lunar\Facades\Taxes;
use Lunar\LunarServiceProvider;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\ProductFeed\ProductFeedServiceProvider;
use Lunar\Review\Mixins\ProductMixin;
use Lunar\Review\Mixins\ProductVariantMixin;
use Lunar\Tests\Core\Stubs\TestTaxDriver;
use Lunar\Tests\Core\Stubs\TestUrlGenerator;
use Lunar\Tests\Core\Stubs\User;
use Lunar\Tests\TestCase as BaseTestCase;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\LaravelBlink\BlinkServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/packages/review/database/migrations');

        Config::set('providers.users.model', User::class);
        Config::set('lunar.urls.generator', TestUrlGenerator::class);
        Config::set('lunar.taxes.driver', 'test');
        Config::set('lunar.media.collection', 'images');
        Config::set('app.url', 'https://shop.test');
        Config::set('lunar.product-feed.product_url.base_url', 'https://shop.test');
        Config::set('lunar.product-feed.product_url.template', '{base_url}/{slug}');
        Config::set('cache.default', 'array');
        Config::set('lunar.product-feed.cache.enabled', true);
        Config::set('lunar.product-feed.cache.ttl', 3600);
        Config::set('lunar.product-feed.cache.key_prefix', 'lunar.product-feed');
        Config::set('lunar.product-feed.cache.store', null);
        Config::set('lunar.product-feed.query.chunk_size', 100);

        Taxes::extend('test', function ($app) {
            return $app->make(TestTaxDriver::class);
        });

        Product::mixin(new ProductMixin);
        ProductVariant::mixin(new ProductVariantMixin);

        activity()->disableLogging();

        $this->freezeTime();

        $this->app->make(\Lunar\ProductFeed\ProductFeedCache::class)->forgetAll();
    }

    protected function getPackageProviders($app)
    {
        return [
            LunarServiceProvider::class,
            ProductFeedServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ActivitylogServiceProvider::class,
            ConverterServiceProvider::class,
            NestedSetServiceProvider::class,
            BlinkServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $this->replaceModelsForTesting();
    }
}
