<?php

namespace Lunar\Analytics;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Lunar\Analytics\Traffic\Filament\Pages\TrafficSourcesPage;

class AnalyticsPlugin implements Plugin
{
    /**
     * Get the plugin identifier.
     */
    public function getId(): string
    {
        return 'lunar-analytics';
    }

    /**
     * Boot the plugin.
     */
    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Register the "Analytics" navigation group and each feature's pages and widgets.
     */
    public function register(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make('analytics')
                ->label(fn () => __('lunarpanel.analytics::plugin.navigation.group')),
        ])->pages([
            TrafficSourcesPage::class,
        ])->widgets(TrafficSourcesPage::trafficWidgets());
    }

    /**
     * Create a new plugin instance.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Return the panel unchanged.
     */
    public function panel(Panel $panel): Panel
    {
        return $panel;
    }
}
