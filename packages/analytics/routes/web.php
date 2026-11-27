<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Support\Facades\Route;
use Lunar\Analytics\Traffic\Http\Controllers\ConfirmTrafficVisitController;

Route::post('lunar/presence', ConfirmTrafficVisitController::class)
    ->middleware(EncryptCookies::class)
    ->name('lunar.analytics.traffic.presence');
