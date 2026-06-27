<?php

use EventSolutions\NeventoSocialite\Http\Controllers\OAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/redirect', [OAuthController::class, 'redirect'])->name('nevento.redirect');
Route::get('/auth/callback', [OAuthController::class, 'callback'])->name('nevento.callback');
Route::post('/auth/logout', [OAuthController::class, 'logout'])->name('nevento.logout');
