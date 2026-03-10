<?php

namespace Lunar\Tests\review\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Review\ReviewPlugin;

class ReviewPanelTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(
            fn ($panel) => $panel->plugin(ReviewPlugin::make())
        );

        LunarPanel::register();
    }
}
