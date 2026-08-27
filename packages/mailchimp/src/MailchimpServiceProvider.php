<?php

namespace Lunar\Mailchimp;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\ERP\Events\OrderPlacedEvent;
use Lunar\Events\Marketing\CustomerMarketingConsentGranted;
use Lunar\Events\Marketing\CustomerMarketingProfileUpdated;
use Lunar\Events\Marketing\StorefrontMarketingEventOccurred;
use Lunar\Mailchimp\Commands\CreateMailchimpStoreCommand;
use Lunar\Mailchimp\Commands\SetupMailchimpMergeFieldsCommand;
use Lunar\Mailchimp\Commands\SyncAllOrdersToMailchimpCommand;
use Lunar\Mailchimp\Commands\SyncAllProductsToMailchimpCommand;
use Lunar\Mailchimp\Commands\SyncAllUserLanguagesToMailchimpCommand;
use Lunar\Mailchimp\Commands\SyncAllUsersToMailchimpCommand;
use Lunar\Mailchimp\Listeners\SubscribeCustomerOnMarketingConsentGranted;
use Lunar\Mailchimp\Listeners\SyncCustomerOnMarketingProfileUpdated;
use Lunar\Mailchimp\Listeners\SyncOrderOnPlacement;
use Lunar\Mailchimp\Listeners\TrackEventOnStorefrontMarketingEventOccurred;
use Lunar\Mailchimp\Observers\CartLineObserver;
use Lunar\Models\CartLine;

class MailchimpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mailchimp.php', 'lunar.mailchimp');
        $this->registerDeferredQueueConnection();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConsoleCommands();
        $this->publishAssets();
        $this->registerObservers();
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

    /**
     * Register marketing lifecycle listeners.
     */
    protected function registerListeners(): void
    {
        Event::listen(CustomerMarketingConsentGranted::class, SubscribeCustomerOnMarketingConsentGranted::class);
        Event::listen(CustomerMarketingProfileUpdated::class, SyncCustomerOnMarketingProfileUpdated::class);
        Event::listen(StorefrontMarketingEventOccurred::class, TrackEventOnStorefrontMarketingEventOccurred::class);
        Event::listen(OrderPlacedEvent::class, SyncOrderOnPlacement::class);
    }

    /**
     * Publish package config and migrations.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../config/mailchimp.php' => config_path('lunar/mailchimp.php'),
        ], 'lunar.mailchimp.config');
    }

    /**
     * Register Artisan console commands.
     */
    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateMailchimpStoreCommand::class,
                SetupMailchimpMergeFieldsCommand::class,
                SyncAllUsersToMailchimpCommand::class,
                SyncAllUserLanguagesToMailchimpCommand::class,
                SyncAllOrdersToMailchimpCommand::class,
                SyncAllProductsToMailchimpCommand::class,
            ]);
        }
    }

    /**
     * Register observers for models.
     */
    protected function registerObservers()
    {
        CartLine::observe(CartLineObserver::class);
    }
}
