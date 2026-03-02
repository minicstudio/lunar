<?php

namespace Lunar\Localities;

use Illuminate\Support\ServiceProvider;
use Lunar\Facades\ModelManifest;

class LocalitiesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerModelManifest();
    }

    /**
     * Register model manifest directory.
     */
    protected function registerModelManifest(): void
    {
        ModelManifest::addDirectory(__DIR__ . '/Models');
    }
}
