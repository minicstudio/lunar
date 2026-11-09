<?php

namespace Lunar\Analytics;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Analytics\Database\State\EnsureAnalyticsPermissions;
use Lunar\Analytics\Traffic\Contracts\ClassifiesTrafficSource;
use Lunar\Analytics\Traffic\Observers\StampCartWithTrafficSource;
use Lunar\Analytics\Traffic\Support\TrafficSourceClassifier;
use Lunar\Models\Cart;

class AnalyticsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/analytics.php', 'lunar.analytics');

        $this->registerTrafficServices();
        $this->registerPermissionTranslations();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadPackageAssets();
        $this->publishAssets();
        $this->registerObservers();
        $this->registerStateListeners();
    }

    /**
     * Bind the traffic classifier to the configured class.
     */
    protected function registerTrafficServices(): void
    {
        $this->app->bind(ClassifiesTrafficSource::class, function (Application $app): ClassifiesTrafficSource {
            return $app->make(config('lunar.analytics.traffic.classifier', TrafficSourceClassifier::class));
        });
    }

    /**
     * Register model observers. `Cart::observe()` follows a model-manifest replacement.
     */
    protected function registerObservers(): void
    {
        Cart::observe(StampCartWithTrafficSource::class);
    }

    /**
     * Register state listeners for migration events.
     */
    protected function registerStateListeners(): void
    {
        foreach ([EnsureAnalyticsPermissions::class] as $state) {
            $class = new $state;

            Event::listen([MigrationsStarted::class], [$class, 'prepare']);
            Event::listen([MigrationsEnded::class, NoPendingMigrations::class], [$class, 'run']);
        }
    }

    /**
     * Merge analytics permission labels into the Lunar Panel auth translations.
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
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunarpanel.analytics');

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
            __DIR__.'/../config/analytics.php' => config_path('lunar/analytics.php'),
        ], 'lunar.analytics.config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'lunar.analytics.migrations');
    }
}
