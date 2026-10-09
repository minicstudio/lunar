<?php

namespace Lunar\Tests\Feedback\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Feedback\FeedbackPlugin;

class FeedbackPanelTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::panel(
            fn ($panel) => $panel->plugin(FeedbackPlugin::make())
        );

        LunarPanel::register();
    }
}
