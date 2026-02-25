<?php

namespace Lunar\Addons\Shipping;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Addons\Shipping\Console\SyncShippingCitiesCommand;
use Lunar\Addons\Shipping\Console\SyncShippingCountiesCommand;
use Lunar\Addons\Shipping\Console\SyncShippingLockersCommand;
use Lunar\Addons\Shipping\Exceptions\ShippingInitializationException;
use Lunar\Facades\ModelManifest;
use Minic\LunarFrontend\Domains\Order\Filament\Extensions\ShippingExtension;

class ShippingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/shipping.php', 'lunar.shipping');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConsoleCommands();
        $this->registerModelManifest();
        $this->loadPackageAssets();
        $this->publishAssets();
        $this->registerShippingProviders();
        $this->registerSchedule();
        $this->extendAdminPanel();
    }

    /**
     * Load package assets like migrations and translations.
     */
    protected function loadPackageAssets(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'lunarpanel.shipping');

        if (! config('lunar.database.disable_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Publish package config and migrations.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__ . '/../config/shipping.php' => config_path('lunar/shipping.php'),
        ], 'lunar.shipping.config');

        // loop through the shipping providers and publish their configs
        $shippingProviders = config('lunar.shipping.providers', []);
        foreach ($shippingProviders as $provider) {
            $this->publishes([
                __DIR__ . "/Providers/{$provider}/config.php" => config_path("lunar/shipping/{$provider}.php"),
            ], 'lunar.shipping.config');
        }

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'lunar.shipping.migrations');
    }

    /**
     * Register model manifest directory.
     */
    protected function registerModelManifest(): void
    {
        ModelManifest::addDirectory(__DIR__ . '/Models');
    }

    /**
     * Register Artisan console commands.
     */
    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncShippingCitiesCommand::class,
                SyncShippingCountiesCommand::class,
                SyncShippingLockersCommand::class,
            ]);
        }
    }

    /**
     * Register the shipping providers.
     */
    protected function registerShippingProviders(): void
    {
        if (! config('lunar.shipping.enabled')) {
            return;
        }

        $providers = config('lunar.shipping.providers', []);

        foreach ($providers as $providerKey) {
            $providerConfigPath = "lunar.shipping.{$providerKey}";

            $providerConfig = config($providerConfigPath);

            if (! isset($providerConfig['enabled']) || ! $providerConfig['enabled']) {
                throw new ShippingInitializationException("Shipping provider [{$providerKey}] is added to the list of shipping providers but its config file is missing.");
            }

            if (! isset($providerConfig['provider_class'], $providerConfig['client_class'])) {
                throw new ShippingInitializationException("Shipping provider [{$providerKey}] is missing required classes.");
            }

            $providerClass = $providerConfig['provider_class'];
            $clientClass = $providerConfig['client_class'];

            if (! class_exists($providerClass)) {
                throw new ShippingInitializationException("Shipping provider class [{$providerClass}] not found.");
            }

            if (! class_exists($clientClass)) {
                throw new ShippingInitializationException("Shipping client class [{$clientClass}] not found for provider [{$providerKey}].");
            }

            $this->app->singleton($providerClass, function () use ($clientClass, $providerClass) {
                return new $providerClass(new $clientClass);
            });
        }
    }

    /**
     * Register the schedule.
     */
    protected function registerSchedule(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('lunar:sync-shipping-lockers')->dailyAt('03:00')->when(function () {
                return config('lunar.shipping.locker_enabled');
            });
        });
    }

    /**
     * Extend the admin panel with custom assets.
     */
    protected function extendAdminPanel(): void
    {
        LunarPanel::extensions([
            \Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder::class => ShippingExtension::class,
        ]);
    }
}
