<?php

return [
    'enabled' => env('BLOG_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | URL Generator
    |--------------------------------------------------------------------------
    |
    | Here you can specify a class to automatically generate URLs for blog models
    | which implement the `HasUrls` trait. If left null no generation will happen.
    | You are free to use your own generator, or you can use the one that
    | ships with the Blog package, which by default will try the title
    | attribute first, then fall back to name.
    |
    */
    'urlGenerator' => \Lunar\Blog\Generators\UrlGenerator::class,
];
