<?php

namespace Lunar\Blog;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Lunar\Blog\Filament\Resources\BlogCategoryResource;
use Lunar\Blog\Filament\Resources\BlogPostResource;

class BlogPlugin implements Plugin
{
    public function getId(): string
    {
        return 'blog';
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function register(Panel $panel): void
    {
        if (! config('lunar.blog.enabled')) {
            return;
        }

        $panel->navigationGroups([
            NavigationGroup::make('blog')
                ->label(
                    fn () => __('lunarpanel.blog::plugin.navigation.group')
                ),
        ])->resources([
            BlogCategoryResource::class,
            BlogPostResource::class,
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
