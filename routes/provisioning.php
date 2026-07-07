<?php

declare(strict_types=1);

use EventSolutions\NeventoSocialite\Http\Controllers\ProvisioningController;
use EventSolutions\NeventoSocialite\Http\Middleware\VerifyProvisioningSecret;
use Illuminate\Support\Facades\Route;

/*
 * Stateless internal provisioning API, called server-to-server by the Nevento
 * IDP. Deliberately NOT in the "web" group: no session, no CSRF, no tenancy
 * initialization — auth is the per-app Bearer secret only.
 */
Route::middleware([VerifyProvisioningSecret::class])->group(function (): void {
    Route::get('/internal/health', [ProvisioningController::class, 'health'])
        ->name('nevento.provisioning.health');

    Route::post('/internal/tenants/install', [ProvisioningController::class, 'install'])
        ->name('nevento.provisioning.install');
    Route::post('/internal/tenants/suspend', [ProvisioningController::class, 'suspend'])
        ->name('nevento.provisioning.suspend');
    Route::post('/internal/tenants/unsuspend', [ProvisioningController::class, 'unsuspend'])
        ->name('nevento.provisioning.unsuspend');
    Route::delete('/internal/tenants/uninstall', [ProvisioningController::class, 'uninstall'])
        ->name('nevento.provisioning.uninstall');
    Route::get('/internal/tenants/status/{tenant_key}', [ProvisioningController::class, 'status'])
        ->name('nevento.provisioning.status');
});
