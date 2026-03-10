<?php

namespace Lunar\Review;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Facades\AttributeManifest;
use Lunar\Facades\ModelManifest;
use Lunar\Models\Channel;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Review\Console\ReviewRequestEmailCommand;
use Lunar\Review\Console\RunReviewSeederCommand;
use Lunar\Review\Database\State\EnsureReviewPermissions;
use Lunar\Review\Mixins\ChannelMixin;
use Lunar\Review\Mixins\ProductMixin;
use Lunar\Review\Mixins\ProductVariantMixin;
use Lunar\Review\Models\Review;

class ReviewServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/review.php', 'lunar.review');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConsoleCommands();
        $this->registerModelManifest();
        $this->registerAttributeManifest();
        $this->loadPackageAssets();
        $this->publishAssets();
        $this->registerRelations();
        $this->registerModelMixins();
        $this->registerMediaDefinitions();
        $this->registerPathGenerators();
        $this->registerStateListeners();
        $this->registerMorphMap();
    }

    /**
     * Load package assets like migrations and translations.
     */
    protected function loadPackageAssets(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lunarpanel.review');

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
            __DIR__.'/../config/review.php' => config_path('lunar/review.php'),
        ], 'lunar.review.config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'lunar.review.migrations');
    }

    /**
     * Register dynamic model relations.
     */
    protected function registerRelations(): void
    {
        $userModel = config('auth.providers.users.model');

        $userModel::resolveRelationUsing('reviews', function ($user) {
            return $user->hasMany(Review::class);
        });

        Order::resolveRelationUsing('reviews', function ($order) {
            return $order->hasMany(Review::class);
        });
    }

    /**
     * Register morph map for polymorphic relations.
     */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            'review' => Review::modelClass(),
        ]);
    }

    /**
     * Register model manifest directory.
     */
    protected function registerModelManifest(): void
    {
        ModelManifest::addDirectory(__DIR__.'/Models');
    }

    /**
     * Register attribute manifest types.
     */
    protected function registerAttributeManifest(): void
    {
        AttributeManifest::addType(Review::class);
    }

    /**
     * Register console commands.
     */
    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunReviewSeederCommand::class,
                ReviewRequestEmailCommand::class,
            ]);
        }
    }

    /**
     * Register mixins for reviewable models.
     */
    protected function registerModelMixins(): void
    {
        Channel::mixin(new ChannelMixin);
        Product::mixin(new ProductMixin);
        ProductVariant::mixin(new ProductVariantMixin);
    }

    /**
     * Register media definitions for the Review model.
     *
     * Only sets the definition if not already configured by the user.
     */
    protected function registerMediaDefinitions(): void
    {
        $definitions = config('lunar.media.definitions', []);

        if (! isset($definitions['review'])) {
            $definitions['review'] = config('lunar.review.media_definitions');
            config(['lunar.media.definitions' => $definitions]);
        }
    }

    /**
     * Register state listeners for migration events.
     */
    protected function registerStateListeners(): void
    {
        $states = [
            EnsureReviewPermissions::class,
        ];

        foreach ($states as $state) {
            $class = new $state;

            Event::listen(
                [MigrationsStarted::class],
                [$class, 'prepare']
            );

            Event::listen(
                [MigrationsEnded::class, NoPendingMigrations::class],
                [$class, 'run']
            );
        }
    }

    /**
     * Register path generators for the Review model.
     *
     * Merges Review path generators with existing media-library config.
     * User-configured generators take precedence.
     */
    protected function registerPathGenerators(): void
    {
        $existingGenerators = config('media-library.custom_path_generators', []);
        $reviewPathGenerator = config('lunar.review.path_generator');

        if ($reviewPathGenerator && ! isset($existingGenerators[Review::class])) {
            $existingGenerators[Review::class] = $reviewPathGenerator;
        }

        config(['media-library.custom_path_generators' => $existingGenerators]);
    }
}
