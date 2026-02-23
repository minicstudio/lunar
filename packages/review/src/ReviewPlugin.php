<?php

namespace Lunar\Review;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Lunar\Admin\Support\Facades\LunarPanel;

class ReviewPlugin implements Plugin
{
    public function getId(): string
    {
        return 'review';
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function register(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make('review')
                ->label(
                    fn() => __('lunarpanel.review::plugin.navigation.group')
                ),
        ])->resources([
            \Lunar\Review\Filament\Resources\ReviewResource::class,
        ]);

        LunarPanel::extensions([
            \Lunar\Admin\Filament\Resources\OrderResource::class => \Lunar\Review\Filament\Resources\OrderResource::class,
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
