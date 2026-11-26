<?php

namespace Lunar\Tests\ProductImport\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\ProductImport\ProductImportPlugin;

class ProductImportPanelTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(
            fn ($panel) => $panel->plugin(ProductImportPlugin::make())
        );

        LunarPanel::register();
    }
}
