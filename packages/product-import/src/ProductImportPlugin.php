<?php

namespace Lunar\ProductImport;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Lunar\Admin\Filament\Resources\ProductResource\Pages\ListProducts;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\ProductImport\Filament\Extensions\ListProductsExtension;
use Lunar\ProductImport\Filament\Resources\ProductImportResource;

class ProductImportPlugin implements Plugin
{
    /**
     * Get the plugin identifier.
     */
    public function getId(): string
    {
        return 'lunar-product-import';
    }

    /**
     * Boot the plugin.
     */
    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Register the import resource and the product list extension.
     */
    public function register(Panel $panel): void
    {
        $panel->resources([
            ProductImportResource::class,
        ]);

        LunarPanel::extensions([
            ListProducts::class => ListProductsExtension::class,
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
