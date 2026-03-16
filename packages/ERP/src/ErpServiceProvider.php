<?php

namespace Lunar\ERP;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\ERP\Console\SyncAttributesCommand;
use Lunar\ERP\Console\SyncErpOrdersCommand;
use Lunar\ERP\Console\SyncErpProductsCommand;
use Lunar\ERP\Console\SyncErpStockCommand;
use Lunar\ERP\Console\SyncLocalitiesCommand;
use Lunar\ERP\Filament\Extensions\ShippingExtension;
use Lunar\ERP\Observers\OrderObserver;
use Lunar\Facades\ModelManifest;
use Lunar\Models\Order;

class ErpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/erp.php', 'lunar.erp');
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
        $this->registerErpProviders();
        $this->registerObservers();
        $this->registerSchedule();
        $this->extendAdminPanel();
    }

    /**
     * Register Artisan console commands.
     */
    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncAttributesCommand::class,
                SyncErpOrdersCommand::class,
                SyncErpProductsCommand::class,
                SyncErpStockCommand::class,
                SyncLocalitiesCommand::class,
            ]);
        }
    }

    /**
     * Register model manifest directory.
     */
    protected function registerModelManifest(): void
    {
        ModelManifest::addDirectory(__DIR__.'/Models');
    }

    /**
     * Load package assets like migrations and translations.
     */
    protected function loadPackageAssets(): void
    {
        if (! config('lunar.database.disable_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Publish package config and migrations.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../config/erp.php' => config_path('lunar/erp.php'),
        ], 'lunar.erp.config');

        // loop through the ERP providers and publish their configs
        $erpProviders = config('lunar.erp.providers', []);
        foreach ($erpProviders as $provider) {
            $this->publishes([
                __DIR__."/Providers/{$provider}/config.php" => config_path("lunar/erp/{$provider}.php"),
            ], 'lunar.erp.config');
        }

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'lunar.erp.migrations');
    }

    /**
     * Register the ERP providers.
     */
    protected function registerErpProviders(): void
    {
        if (! config('lunar.erp.enabled')) {
            return;
        }

        $providers = config('lunar.erp.providers', []);

        foreach ($providers as $providerKey) {
            $providerConfigPath = "lunar.erp.{$providerKey}";

            $providerConfig = config($providerConfigPath);

            if (! isset($providerConfig['enabled']) || ! $providerConfig['enabled']) {
                throw new \Lunar\ERP\Exceptions\ErpInitializationException("ERP provider [{$providerKey}] is added to the list of ERP providers but its config file is missing.");
            }

            if (! isset($providerConfig['provider_class'], $providerConfig['client_class'])) {
                throw new \Lunar\ERP\Exceptions\ErpInitializationException("ERP provider [{$providerKey}] is missing required classes.");
            }

            $providerClass = $providerConfig['provider_class'];
            $clientClass = $providerConfig['client_class'];

            if (! class_exists($providerClass)) {
                throw new \Lunar\ERP\Exceptions\ErpInitializationException("ERP provider class [{$providerClass}] not found.");
            }

            if (! class_exists($clientClass)) {
                throw new \Lunar\ERP\Exceptions\ErpInitializationException("ERP client class [{$clientClass}] not found for provider [{$providerKey}].");
            }

            $this->app->bind($providerClass, function ($app) use ($clientClass, $providerClass) {
                return $app->make($providerClass, [
                    'client' => $app->make($clientClass),
                ]);
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

            if (config('lunar.erp.enabled')) {
                $erpSchedule = config('lunar.erp.schedule', []);

                $schedule->command('erp:sync-products')->cron($erpSchedule['products'])->when(function () {
                    return ! empty(config('lunar.erp.sync.products'));
                });

                $schedule->command('erp:sync-order-statuses')->cron($erpSchedule['orders'])->when(function () {
                    return ! empty(config('lunar.erp.sync.orders'));
                });

                $schedule->command('erp:sync-stock')->cron($erpSchedule['stock'])->when(function () {
                    return ! empty(config('lunar.erp.sync.stock'));
                });

                $schedule->command('erp:sync-localities')->cron($erpSchedule['localities'])->when(function () {
                    return ! empty(config('lunar.erp.sync.localities'));
                });

                $schedule->command('erp:sync-attributes')->cron($erpSchedule['attributes'])->when(function () {
                    return ! empty(config('lunar.erp.sync.attributes'));
                });
            }
        });
    }

    /**
     * Register observers for models.
     */
    protected function registerObservers()
    {
        Order::observe(OrderObserver::class);
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
