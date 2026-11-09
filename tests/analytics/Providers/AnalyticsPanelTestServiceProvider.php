<?php

namespace Lunar\Tests\Analytics\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Analytics\AnalyticsPlugin;

class AnalyticsPanelTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(
            fn ($panel) => $panel->plugin(AnalyticsPlugin::make())
        );

        LunarPanel::register();
    }
}
