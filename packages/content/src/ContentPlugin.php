<?php

namespace Lunar\Content;

use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Lunar\Content\Filament\Resources\AnnouncementResource;
use Lunar\Content\Filament\Resources\ContactInfoResource;
use Lunar\Content\Filament\Resources\FaqItemResource;
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
        $resources = $this->enabledResources();

        if ($resources === []) {
            return;
        }

        $panel->navigationGroups([
            NavigationGroup::make('content')
                ->label(
                    fn () => __('lunarpanel.content::plugin.navigation.group')
                )
                ->collapsed(),
        ])->resources($resources);
    }

    /**
     * Filament resources enabled via `lunar.content.resources`.
     *
     * @return array<int, class-string>
     */
    protected function enabledResources(): array
    {
        $map = [
            'announcement' => AnnouncementResource::class,
            'hero' => HeroResource::class,
            'menu_item' => MenuItemResource::class,
            'popup' => PopupResource::class,
            'faq_item' => FaqItemResource::class,
            'contact_info' => ContactInfoResource::class,
        ];

        $enabled = config('lunar.content.resources', []);

        return array_values(array_filter(
            $map,
            fn (string $class, string $key): bool => (bool) ($enabled[$key] ?? true),
            ARRAY_FILTER_USE_BOTH
        ));
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
