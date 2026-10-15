<?php

use EventSolutions\NeventoSocialite\System\ReportController;
use Illuminate\Support\Facades\Route;

// The report button's endpoint. Answers 404 until SYSTEM_TOKEN is set.
Route::middleware(['web', 'auth', 'throttle:10,1'])->group(function () {
    Route::post('/nevento/report', [ReportController::class, 'store'])->name('nevento.report');
});
