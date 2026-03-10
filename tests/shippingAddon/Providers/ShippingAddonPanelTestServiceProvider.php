<?php

namespace Lunar\Tests\shippingAddon\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;

class ShippingAddonPanelTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        LunarPanel::register();
    }
}
