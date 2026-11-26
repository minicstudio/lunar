<?php

namespace Lunar\ProductImport;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Lunar\ProductImport\Console\PruneProductImports;
use Lunar\ProductImport\Database\State\EnsureProductImportPermissions;
use Lunar\ProductImport\Filament\Livewire\ImportProgress;
use Lunar\ProductImport\Filament\Livewire\ImportResult;
use Lunar\ProductImport\Filament\Livewire\PreviewStep;
use Lunar\ProductImport\Filament\Livewire\SheetMappingStep;
use Lunar\ProductImport\Filament\Livewire\ValueReviewStep;
use Lunar\ProductImport\Support\TargetFieldRegistry;

class ProductImportServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/product-import.php', 'lunar.product-import');

        $this->app->scoped(TargetFieldRegistry::class);

        $this->registerPermissionTranslations();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadPackageAssets();
        $this->publishAssets();
        $this->registerStateListeners();
        $this->registerConsoleCommands();
        $this->registerSchedule();
        $this->registerLivewireComponents();
    }

    /**
     * Register the import step components.
     */
    protected function registerLivewireComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        Livewire::component('lunar-product-import.sheet-mapping-step', SheetMappingStep::class);
        Livewire::component('lunar-product-import.value-review-step', ValueReviewStep::class);
        Livewire::component('lunar-product-import.preview-step', PreviewStep::class);
        Livewire::component('lunar-product-import.import-progress', ImportProgress::class);
        Livewire::component('lunar-product-import.import-result', ImportResult::class);
    }

    /**
     * Register console commands.
     */
    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneProductImports::class,
            ]);
        }
    }

    /**
     * Schedule pruning while the feature is enabled.
     */
    public function registerSchedule(): void
    {
        $this->app->booted(function (): void {
            if (! config('lunar.product-import.enabled', false)) {
                return;
            }

            if ($cron = config('lunar.product-import.schedule.prune')) {
                $this->app->make(Schedule::class)->command('product-import:prune')->cron($cron);
            }
        });
    }

    /**
     * Register state listeners for migration events.
     */
    protected function registerStateListeners(): void
    {
        foreach ([EnsureProductImportPermissions::class] as $state) {
            $class = new $state;

            Event::listen([MigrationsStarted::class], [$class, 'prepare']);
            Event::listen([MigrationsEnded::class, NoPendingMigrations::class], [$class, 'run']);
        }
    }

    /**
     * Merge the import permission labels into the Lunar Panel auth translations.
     *
     * Load `lunarpanel::auth` first. `Translator::addLines()` marks the group as
     * loaded, so calling it too early would skip the admin language files and
     * Access Control would fall back to permission handles.
     */
    protected function registerPermissionTranslations(): void
    {
        $this->app->booted(function (): void {
            $translator = $this->app['translator'];

            foreach (['en', 'hu', 'ro'] as $locale) {
                $path = __DIR__."/../resources/lang/vendor/lunarpanel/{$locale}/auth.php";

                if (! is_file($path)) {
                    continue;
                }

                $lines = [];

                foreach (require $path as $key => $value) {
                    if (! is_string($key) || ! is_string($value)) {
                        continue;
                    }

                    $lines['auth.'.$key] = $value;
                }

                $translator->load('lunarpanel', 'auth', $locale);
                $translator->addLines($lines, $locale, 'lunarpanel');
            }
        });
    }

    /**
     * Load package migrations and translations.
     */
    protected function loadPackageAssets(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunarpanel.product-import');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'lunarpanel.product-import');

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
            __DIR__.'/../config/product-import.php' => config_path('lunar/product-import.php'),
        ], 'lunar.product-import.config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'lunar.product-import.migrations');
    }
}
