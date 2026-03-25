<?php

namespace Lunar\Mailchimp;

use Illuminate\Support\ServiceProvider;
use Lunar\Facades\ModelManifest;
use Lunar\Mailchimp\Commands\CreateMailchimpStoreCommand;
use Lunar\Mailchimp\Commands\SetupMailchimpMergeFieldsCommand;
use Lunar\Mailchimp\Commands\SyncAllOrdersToMailchimpCommand;
use Lunar\Mailchimp\Commands\SyncAllProductsToMailchimpCommand;
use Lunar\Mailchimp\Commands\SyncAllUsersToMailchimpCommand;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConsoleCommands();
        $this->publishAssets();
        $this->registerObservers();
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
