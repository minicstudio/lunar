<?php

namespace Lunar\ERP;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\LunarPanelManager;
use Lunar\ERP\Console\SyncAttributesCommand;
use Lunar\ERP\Console\SyncErpOrdersCommand;
use Lunar\ERP\Console\SyncErpProductsCommand;
use Lunar\ERP\Console\SyncErpStockCommand;
use Lunar\ERP\Console\SyncLocalitiesCommand;
use Lunar\ERP\Filament\Extensions\ShippingExtension;
use Lunar\ERP\Observers\OrderObserver;
use Lunar\ERP\Services\ErpService;
use Lunar\Facades\ModelManifest;
use Lunar\Models\Order;

class ErpServiceProvider extends ServiceProvider
{
    /**
     * Scheduled sync commands keyed by their `lunar.erp.sync` / `lunar.erp.schedule` feature.
     *
     * @var array<string, string>
     */
    protected const SYNC_COMMANDS = [
        'products' => 'erp:sync-products',
        'orders' => 'erp:sync-order-statuses',
        'stock' => 'erp:sync-stock',
        'localities' => 'erp:sync-localities',
        'attributes' => 'erp:sync-attributes',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/erp.php', 'lunar.erp');

        $this->extendAdminPanel();
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

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunarpanel.erp');
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

            $this->app->bind($providerClass, function () use ($clientClass, $providerClass) {
                return new $providerClass(new $clientClass);
            });
        }
    }

    /**
     * Register the schedule.
     *
     * A sync command is only scheduled when at least one enabled provider is
     * allowed for that feature, so projects using e.g. Smartbill (billing only)
     * do not get Magister-only sync commands registered and skipped every run.
     *
     * @throws ErpInitializationException
     */
    protected function registerSchedule(): void
    {
        $this->app->booted(function () {
            if (! config('lunar.erp.enabled')) {
                return;
            }

            $schedule = $this->app->make(Schedule::class);
            $erpService = $this->app->make(ErpService::class);
            $cronExpressions = config('lunar.erp.schedule', []);

            foreach (self::SYNC_COMMANDS as $feature => $command) {
                if (empty($erpService->getAllowedProviders('sync', $feature))) {
                    continue;
                }

                if (empty($cronExpressions[$feature])) {
                    throw new \Lunar\ERP\Exceptions\ErpInitializationException("ERP sync feature [{$feature}] has providers configured but no schedule expression.");
                }

                $schedule->command($command)->cron($cronExpressions[$feature]);
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
        $this->app->resolving('lunar-panel', function (LunarPanelManager $panel): void {
            $panel->extensions([
                ManageOrder::class => ShippingExtension::class,
            ]);
        });
    }
}
