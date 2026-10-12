<?php

namespace Lunar\Feedback;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Lunar\Feedback\Filament\Resources\FeedbackResource;

class FeedbackPlugin implements Plugin
{
    /**
     * Get the plugin identifier.
     */
    public function getId(): string
    {
        return 'feedback';
    }

    /**
     * Boot the plugin.
     */
    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Register the plugin resources on the panel.
     */
    public function register(Panel $panel): void
    {
        $panel->resources([
            FeedbackResource::class,
        ]);
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
