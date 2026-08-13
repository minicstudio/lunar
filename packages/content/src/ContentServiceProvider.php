<?php

namespace Lunar\Content;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Content\Database\State\EnsureContentPermissions;
use Lunar\Content\Models\ContentBlock;
use Lunar\Content\Observers\ContentBlockObserver;

class ContentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/content.php', 'lunar.content');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadPackageAssets();
        $this->publishAssets();
        $this->registerObservers();
        $this->registerMediaDefinitions();
        $this->registerPermissionTranslations();
        $this->registerStateListeners();
    }

    /**
     * Load package assets like translations.
     */
    protected function loadPackageAssets(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunarpanel.content');
    }

    /**
     * Publish package config.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../config/content.php' => config_path('lunar/content.php'),
        ], 'lunar.content.config');
    }

    /**
     * Register model observers.
     */
    protected function registerObservers(): void
    {
        ContentBlock::observe(ContentBlockObserver::class);
    }

    /**
     * Register media definitions for the ContentBlock model.
     *
     * Only sets the definition if not already configured by the user.
     */
    protected function registerMediaDefinitions(): void
    {
        $definitions = config('lunar.media.definitions', []);

        if (! isset($definitions['content_block'])) {
            $definitions['content_block'] = config('lunar.content.media_definitions');
            config(['lunar.media.definitions' => $definitions]);
        }
    }

    /**
     * Merge Content permission labels into the Lunar Panel auth translations.
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
     * Register state listeners for migration events.
     */
    protected function registerStateListeners(): void
    {
        $state = new EnsureContentPermissions;

        Event::listen(
            [MigrationsStarted::class],
            [$state, 'prepare']
        );

        Event::listen(
            [MigrationsEnded::class, NoPendingMigrations::class],
            [$state, 'run']
        );
    }
}
