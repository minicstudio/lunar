<?php

namespace Lunar\Content;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Lunar\Content\Filament\Resources\AnnouncementResource;
use Lunar\Content\Filament\Resources\HeroResource;
use Lunar\Content\Filament\Resources\MenuItemResource;
use Lunar\Content\Filament\Resources\PopupResource;

class ContentPlugin implements Plugin
{
    public function getId(): string
    {
        return 'content';
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function register(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make('content')
                ->label(
                    fn () => __('lunarpanel.content::plugin.navigation.group')
                ),
        ])->resources([
            AnnouncementResource::class,
            HeroResource::class,
            MenuItemResource::class,
            PopupResource::class,
        ]);
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel;
    }
}
