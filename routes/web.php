<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployKeyController;
use App\Http\Controllers\DeploymentController;
use App\Http\Controllers\EnvVarController;
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
        Route::post('deployments', [DeploymentController::class, 'store'])->name('deployments.store');
        Route::get('deployments/{deployment:number}', [DeploymentController::class, 'show'])->name('deployments.show');
        Route::post('deployments/{deployment:number}/rollback', [DeploymentController::class, 'rollback'])->name('deployments.rollback');
        Route::post('env', [EnvVarController::class, 'store'])->name('env.store');
        Route::delete('env/{key}', [EnvVarController::class, 'destroy'])->where('key', '[A-Za-z_][A-Za-z0-9_]*')->name('env.destroy');
        Route::post('deploy-key', [DeployKeyController::class, 'store'])->name('deploy-key.store');
    });

    Route::get('/settings/audit-log', [AuditLogController::class, 'index'])->name('settings.audit-log');
});
