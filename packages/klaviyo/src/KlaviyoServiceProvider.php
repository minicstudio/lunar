<?php

namespace Lunar\Klaviyo;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\Events\Marketing\CustomerMarketingConsentGranted;
use Lunar\Events\Marketing\CustomerMarketingProfileUpdated;
use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Events\ProductDeletedEvent;
use Lunar\Events\ProductPublished;
use Lunar\Events\ProductUpdatedEvent;
use Lunar\Klaviyo\Commands\SyncAllProductsToKlaviyoCommand;
use Lunar\Klaviyo\Listeners\SubscribeProfileOnMarketingConsentGranted;
use Lunar\Klaviyo\Listeners\SyncOrderOnPlacement;
use Lunar\Klaviyo\Listeners\SyncProductOnDeleted;
use Lunar\Klaviyo\Listeners\SyncProductOnPublished;
use Lunar\Klaviyo\Listeners\SyncProductOnUpdated;
use Lunar\Klaviyo\Listeners\SyncProfileOnMarketingProfileUpdated;
use Lunar\Klaviyo\Listeners\TrackEventOnStorefrontMarketingEventOccurred;

class KlaviyoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/klaviyo.php', 'lunar.klaviyo');
    }

    public function boot(): void
    {
        $this->registerConsoleCommands();
        $this->publishAssets();
        $this->registerListeners();
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
    }
}
