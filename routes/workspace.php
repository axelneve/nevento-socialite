<?php

use EventSolutions\NeventoSocialite\Http\Controllers\WorkspaceSwitchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/auth/workspace/{workspaceId}/switch', [WorkspaceSwitchController::class, 'switch'])
        ->name('nevento.workspace.switch');
});
