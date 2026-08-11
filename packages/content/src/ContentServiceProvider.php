<?php

namespace Lunar\Content;

use Illuminate\Support\ServiceProvider;
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
}
