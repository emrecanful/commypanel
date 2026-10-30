<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AppDomainController;
use App\Http\Controllers\AppSetupController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployKeyController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\EnvVarController;
use App\Http\Controllers\HttpsController;
use App\Http\Controllers\ServerAddressController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('/setup/{token}', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup/{token}', [SetupController::class, 'store'])->name('setup.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::inertia('/settings/appearance', 'settings/appearance')->name('settings.appearance');
    Route::inertia('/settings/security', 'settings/security')
        ->middleware('password.confirm')
        ->name('settings.security');
    Route::resource('apps', AppController::class)
        ->parameters(['apps' => 'app:slug'])
        ->except(['edit']);

    Route::scopeBindings()->prefix('apps/{app:slug}')->name('apps.')->group(function () {
        Route::get('setup', [AppSetupController::class, 'show'])->name('setup');
        Route::post('setup/check', [AppSetupController::class, 'check'])->name('setup.check');
        Route::post('setup/upload', [AppSetupController::class, 'upload'])->name('setup.upload');
        Route::post('setup/continue', [AppSetupController::class, 'continue'])->name('setup.continue');
        Route::post('setup/back', [AppSetupController::class, 'back'])->name('setup.back');
        Route::post('setup/settings', [AppSetupController::class, 'settings'])->name('setup.settings');
        Route::post('setup/finish', [AppSetupController::class, 'finish'])->name('setup.finish');
        Route::delete('setup', [AppSetupController::class, 'cancel'])->name('setup.cancel');
        Route::post('deployments', [DeploymentController::class, 'store'])->name('deployments.store');
        Route::get('deployments/{deployment:number}', [DeploymentController::class, 'show'])->name('deployments.show');
        Route::post('deployments/{deployment:number}/rollback', [DeploymentController::class, 'rollback'])->name('deployments.rollback');
        Route::post('env', [EnvVarController::class, 'store'])->name('env.store');
        Route::delete('env/{key}', [EnvVarController::class, 'destroy'])->where('key', '[A-Za-z_][A-Za-z0-9_]*')->name('env.destroy');
        Route::post('deploy-key', [DeployKeyController::class, 'store'])->name('deploy-key.store');
        Route::post('domains', [AppDomainController::class, 'store'])->name('domains.store');
        Route::post('certificate', [CertificateController::class, 'store'])->name('certificate.store');
    });

    Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
    Route::post('domains/{domain}/check', [DomainController::class, 'check'])->name('domains.check');
    Route::delete('domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
    Route::put('settings/https', [HttpsController::class, 'update'])->name('settings.https.update');
    Route::put('settings/server-address', [ServerAddressController::class, 'update'])->name('settings.server-address.update');
    Route::post('settings/server-address/detect', [ServerAddressController::class, 'detect'])->name('settings.server-address.detect');

    Route::get('/settings/audit-log', [AuditLogController::class, 'index'])->name('settings.audit-log');
});
