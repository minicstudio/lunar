<?php

namespace Lunar\ProductFeed;

use Illuminate\Support\ServiceProvider;
use Lunar\ProductFeed\Encoders\CsvFeedEncoder;
use Lunar\ProductFeed\Encoders\JsonFeedEncoder;
use Lunar\ProductFeed\Encoders\XmlFeedEncoder;
use Lunar\ProductFeed\Services\ProductFeedService;

class ProductFeedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/product-feed.php', 'lunar.product-feed');

        $this->app->singleton(ProductFeedService::class);
        $this->app->singleton(ProductFeedCache::class);

        $this->app->singleton(ProductFeedEncoderResolver::class, function () {
            return new ProductFeedEncoderResolver([
                'csv' => new CsvFeedEncoder,
                'xml' => new XmlFeedEncoder,
                'json' => new JsonFeedEncoder,
            ]);
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([
            __DIR__.'/../config/product-feed.php' => config_path('lunar/product-feed.php'),
        ], 'lunar.product-feed.config');
    }
}
