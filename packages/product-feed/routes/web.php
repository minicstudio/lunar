<?php

use Illuminate\Support\Facades\Route;
use Lunar\ProductFeed\Http\Controllers\ProductFeedController;

$prefix = trim((string) config('lunar.product-feed.route_prefix', 'feeds'), '/');

// No 'web' middleware: this is a public, cacheable feed for crawlers with no
// need for sessions/cookies/CSRF, and starting a session here would issue a
// Set-Cookie header that defeats HTTP caching and (if a real browser session
// ever hits this URL) would persist into that session.
Route::get("{$prefix}/products/{format}", [ProductFeedController::class, 'show'])
    ->whereIn('format', ['csv', 'xml', 'json'])
    ->name('product-feed.show');
