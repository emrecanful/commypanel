<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::get('/setup/{token}', [SetupController::class, 'show'])->name('setup');
    Route::post('/setup/{token}', [SetupController::class, 'store'])->name('setup.store');
});

Route::middleware('auth')->group(function () {
    Route::inertia('/dashboard', 'dashboard')->name('dashboard');
    Route::inertia('/settings/security', 'settings/security')
        ->middleware('password.confirm')
        ->name('settings.security');
    Route::get('/settings/audit-log', [AuditLogController::class, 'index'])->name('settings.audit-log');
});
