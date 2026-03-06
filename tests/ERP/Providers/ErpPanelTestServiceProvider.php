<?php

namespace Lunar\Tests\ERP\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;

class ErpPanelTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::register();
    }
}
