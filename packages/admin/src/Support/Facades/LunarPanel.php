<?php

namespace Lunar\Admin\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Lunar\Admin\LunarPanelManager register()
 * @method static \Lunar\Admin\LunarPanelManager panel(\Closure $closure)
 * @method static \Filament\Panel getPanel()
 * @method static \Lunar\Admin\LunarPanelManager forceTwoFactorAuth(bool $state = true)
 * @method static \Lunar\Admin\LunarPanelManager disableTwoFactorAuth()
 * @method static \Lunar\Admin\LunarPanelManager extensions(array $extensions)
 * @method static array getExtensions()
 * @method static array getResources()
 * @method static array getPages()
 * @method static array getWidgets()
 * @method static \Lunar\Admin\LunarPanelManager useRoleAsAdmin(array|string $roleHandle)
 * @method static mixed callHook(string $class, object|null $caller, string $hookName, void ...$args)
 *
 * @see \Lunar\Admin\LunarPanelManager
 */
class LunarPanel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'lunar-panel';
    }
}
