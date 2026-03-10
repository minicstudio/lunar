<?php

namespace Lunar\Tests\Blog\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Blog\BlogPlugin;

class BlogPanelTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(
            fn ($panel) => $panel->plugin(BlogPlugin::make())
        );

        LunarPanel::register();
    }
}
