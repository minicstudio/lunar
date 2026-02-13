<?php

namespace Lunar\Blog;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Models\Staff;
use Lunar\Blog\Console\RunLunarBlogSeederCommand;
use Lunar\Blog\Models\BlogCategory;
use Lunar\Blog\Models\BlogPost;
use Lunar\Blog\Observers\BlogPostObserver;
use Lunar\Facades\ModelManifest;
use Lunar\Models\Channel;

class BlogServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/blog.php', 'lunar.blog');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! config('lunar.blog.enabled')) {
            return;
        }

        $this->registerConsoleCommands();
        $this->registerModelManifest();
        $this->loadPackageAssets();
        $this->publishAssets();
        $this->registerObservers();
        $this->registerRelations();
        $this->registerMorphMap();
    }

    /**
     * Load package assets like migrations and translations.
     */
    protected function loadPackageAssets(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'lunarpanel.blog');

        if (! config('lunar.database.disable_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Publish package config and migrations.
     */
    protected function publishAssets(): void
    {
        $this->publishes([
            __DIR__ . '/../config/blog.php' => config_path('lunar/blog.php'),
        ], 'lunar.blog.config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'lunar.blog.migrations');
    }

    /**
     * Register model observers.
     */
    protected function registerObservers(): void
    {
        BlogPost::observe(BlogPostObserver::class);
    }

    /**
     * Register dynamic model relations.
     */
    protected function registerRelations(): void
    {
        Staff::resolveRelationUsing('blogPosts', function ($product) {
            return $product->hasMany(BlogPost::class, 'author_id');
        });

        Channel::resolveRelationUsing('blogCategories', function ($channel) {
            $prefix = config('lunar.database.table_prefix');

            return $channel->morphedByMany(
                BlogCategory::modelClass(),
                'channelable',
                "{$prefix}channelables"
            );
        });

        Channel::resolveRelationUsing('blogPosts', function ($channel) {
            $prefix = config('lunar.database.table_prefix');

            return $channel->morphedByMany(
                BlogPost::modelClass(),
                'channelable',
                "{$prefix}channelables"
            );
        });
    }

    /**
     * Register morph map for polymorphic relations.
     */
    protected function registerMorphMap(): void
    {
        Relation::morphMap([
            'blog_category' => BlogCategory::modelClass(),
            'blog_post' => BlogPost::modelClass(),
        ]);
    }

    /**
     * Register model manifest directory.
     */
    protected function registerModelManifest(): void
    {
        ModelManifest::addDirectory(__DIR__ . '/Models');
    }

    /**
     * Register console commands.
     */
    protected function registerConsoleCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunLunarBlogSeederCommand::class
            ]);
        }
    }
}
