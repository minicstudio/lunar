<?php

namespace Lunar\Loyalty;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;

class LoyaltyPlugin implements Plugin
{
    public function getId(): string
    {
        return 'loyalty';
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function register(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make('loyalty')
                ->label(fn () => __('lunarpanel.loyalty::plugin.navigation.group')),
        ])->resources([
            \Lunar\Loyalty\Filament\Resources\LoyaltyTransactionResource::class,
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
