<?php

namespace Lunar\Klaviyo;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\Events\DiscountUpdatedEvent;
use Lunar\Events\Marketing\CustomerMarketingConsentGranted;
use Lunar\Events\Marketing\CustomerMarketingProfileUpdated;
use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Events\ProductDeletedEvent;
use Lunar\Events\ProductPublished;
use Lunar\Events\ProductUpdatedEvent;
use Lunar\Events\ProductVariantCreatedEvent;
use Lunar\Events\ProductVariantDeletedEvent;
use Lunar\Events\ProductVariantUpdatedEvent;
use Lunar\Klaviyo\Commands\DeleteAllProductsFromKlaviyoCommand;
use Lunar\Klaviyo\Commands\SyncAllProductsToKlaviyoCommand;
use Lunar\Klaviyo\Listeners\CaptureCatalogIdentityOnProductDeleting;
use Lunar\Klaviyo\Listeners\SubscribeProfileOnMarketingConsentGranted;
use Lunar\Klaviyo\Listeners\SyncOrderOnPlacement;
use Lunar\Klaviyo\Listeners\SyncProductOnCollectionsUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnDeleted;
use Lunar\Klaviyo\Listeners\SyncProductOnMediaUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnPublished;
use Lunar\Klaviyo\Listeners\SyncProductOnUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnUrlsUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnVariantCreated;
use Lunar\Klaviyo\Listeners\SyncProductOnVariantDeleted;
use Lunar\Klaviyo\Listeners\SyncProductOnVariantOptionsUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnVariantPricingUpdated;
use Lunar\Klaviyo\Listeners\SyncProductOnVariantUpdated;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountBecameGlobal;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountBecameLimited;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountDeleted;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountLimitationChanged;
use Lunar\Klaviyo\Listeners\SyncProductsOnDiscountUpdated;
use Lunar\Klaviyo\Listeners\SyncProfileOnMarketingProfileUpdated;
use Lunar\Klaviyo\Listeners\TrackEventOnStorefrontMarketingEventOccurred;
use Lunar\Models\Product;

class KlaviyoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/klaviyo.php', 'lunar.klaviyo');
        $this->registerDeferredQueueConnection();
    }

    public function boot(): void
    {
        $this->registerConsoleCommands();
        $this->publishAssets();
        $this->registerListeners();
    }

    /**
     * Register Laravel's deferred queue connection for host applications
     * whose queue configuration predates the deferred driver.
     */
    protected function registerDeferredQueueConnection(): void
    {
        if (config('queue.connections.deferred') === null) {
            config()->set('queue.connections.deferred', [
                'driver' => 'deferred',
            ]);
        }
    }

    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../config/klaviyo.php' => config_path('lunar/klaviyo.php'),
        ], 'lunar.klaviyo.config');
    }

    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncAllProductsToKlaviyoCommand::class,
                DeleteAllProductsFromKlaviyoCommand::class,
            ]);
        }
    }

    protected function registerListeners(): void
    {
        Event::listen(CustomerMarketingConsentGranted::class, SubscribeProfileOnMarketingConsentGranted::class);
        Event::listen(CustomerMarketingProfileUpdated::class, SyncProfileOnMarketingProfileUpdated::class);
        Event::listen(StorefrontMarketingEventOccurred::class, TrackEventOnStorefrontMarketingEventOccurred::class);
        Event::listen(OrderPlacedEvent::class, SyncOrderOnPlacement::class);

        Event::listen(ProductPublished::class, SyncProductOnPublished::class);
        Event::listen(ProductUpdatedEvent::class, SyncProductOnUpdated::class);
        Event::listen(ProductDeletedEvent::class, SyncProductOnDeleted::class);

        Event::listen(ProductVariantCreatedEvent::class, SyncProductOnVariantCreated::class);
        Event::listen(ProductVariantUpdatedEvent::class, SyncProductOnVariantUpdated::class);
        Event::listen(ProductVariantDeletedEvent::class, SyncProductOnVariantDeleted::class);

        // Capture catalog external ids while variants still exist (before ProductObserver deletes them).
        Product::deleting(function (Product $product): void {
            app(CaptureCatalogIdentityOnProductDeleting::class)->handle($product);
        });

        if (class_exists(\Lunar\Admin\Events\ProductVariantPricingUpdated::class)) {
            Event::listen(
                \Lunar\Admin\Events\ProductVariantPricingUpdated::class,
                SyncProductOnVariantPricingUpdated::class
            );
        }

        // Variants table "Save variants" (price/SKU/stock) — price-only edits do not dirty the
        // variant model, so ProductVariantUpdatedEvent never fires for those saves.
        if (class_exists(\Lunar\Admin\Events\ProductVariantOptionsUpdated::class)) {
            Event::listen(
                \Lunar\Admin\Events\ProductVariantOptionsUpdated::class,
                SyncProductOnVariantOptionsUpdated::class
            );
        }

        if (class_exists(\Lunar\Admin\Events\ProductCollectionsUpdated::class)) {
            Event::listen(
                \Lunar\Admin\Events\ProductCollectionsUpdated::class,
                SyncProductOnCollectionsUpdated::class
            );
        }

        // Product media create/edit/bulk-delete — does not dirty the product model.
        if (class_exists(\Lunar\Admin\Events\ModelMediaUpdated::class)) {
            Event::listen(
                \Lunar\Admin\Events\ModelMediaUpdated::class,
                SyncProductOnMediaUpdated::class
            );
        }

        // Product URL/slug create/edit/delete — does not dirty the product model.
        if (class_exists(\Lunar\Admin\Events\ModelUrlsUpdated::class)) {
            Event::listen(
                \Lunar\Admin\Events\ModelUrlsUpdated::class,
                SyncProductOnUrlsUpdated::class
            );
        }

        // Discount field updates (Meta/Google merchant parity) — catalog prices use getCurrentPricesIncTax().
        Event::listen(DiscountUpdatedEvent::class, SyncProductsOnDiscountUpdated::class);

        if (class_exists(\Lunar\Admin\Events\BeforeDiscountLimitationAttached::class)) {
            Event::listen(
                \Lunar\Admin\Events\BeforeDiscountLimitationAttached::class,
                SyncProductsOnDiscountBecameLimited::class
            );
        }

        if (class_exists(\Lunar\Admin\Events\DiscountLimitationAttached::class)) {
            Event::listen(
                \Lunar\Admin\Events\DiscountLimitationAttached::class,
                SyncProductsOnDiscountLimitationChanged::class
            );
        }

        if (class_exists(\Lunar\Admin\Events\DiscountLimitationDetached::class)) {
            Event::listen(
                \Lunar\Admin\Events\DiscountLimitationDetached::class,
                SyncProductsOnDiscountLimitationChanged::class
            );
            Event::listen(
                \Lunar\Admin\Events\DiscountLimitationDetached::class,
                SyncProductsOnDiscountBecameGlobal::class
            );
        }

        if (class_exists(\Lunar\Admin\Events\DiscountDeleted::class)) {
            Event::listen(
                \Lunar\Admin\Events\DiscountDeleted::class,
                SyncProductsOnDiscountDeleted::class
            );
        }
    }
}
