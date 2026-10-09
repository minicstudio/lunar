<?php

namespace Lunar\Feedback;

use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Resources\OrderResource;
use Lunar\Admin\Filament\Resources\OrderResource\Pages\ManageOrder;
use Lunar\Admin\LunarPanelManager;
use Lunar\Feedback\Filament\Extensions\ManageOrderExtension;
use Lunar\Feedback\Filament\Extensions\OrderResourceExtension;
use Lunar\Feedback\Models\Feedback;
use Lunar\Models\Order;

class FeedbackServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/feedback.php', 'lunar.feedback');

        $this->registerAdminExtensions();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadPackageAssets();
        $this->publishAssets();
        $this->registerRelations();
    }

    /**
     * Load package migrations and translations.
     */
    protected function loadPackageAssets(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunarpanel.feedback');

        if (! config('lunar.database.disable_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Publish package config and migrations.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__.'/../config/feedback.php' => config_path('lunar/feedback.php'),
        ], 'lunar.feedback.config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'lunar.feedback.migrations');
    }

    /**
     * Register feedback relations on the order model.
     *
     * Relation resolvers registered on the base Lunar order are inherited by
     * order model replacements (e.g. the storefront order).
     */
    protected function registerRelations(): void
    {
        Order::resolveRelationUsing('feedback', function ($order) {
            return $order->hasMany(Feedback::class, 'order_id');
        });

        Order::resolveRelationUsing('shoppingExperienceFeedback', function ($order) {
            return $order->hasOne(Feedback::class, 'order_id')->where(
                'type',
                config('lunar.feedback.types.checkout_shopping_experience')
            );
        });
    }

    /**
     * Register Lunar admin order extensions when feedback is enabled.
     */
    protected function registerAdminExtensions(): void
    {
        $this->app->resolving('lunar-panel', function (LunarPanelManager $panel): void {
            if (! config('lunar.feedback.enabled')) {
                return;
            }

            $panel->extensions([
                OrderResource::class => OrderResourceExtension::class,
                ManageOrder::class => ManageOrderExtension::class,
            ]);
        });
    }
}
