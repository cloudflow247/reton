<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\AdminAppSettingsController;
use App\Http\Controllers\Web\Admin\AdminDashboardController;
use App\Http\Controllers\Web\Admin\AdminIntegrationsController;
use App\Http\Controllers\Web\Admin\AdminPlatformSettingsController;
use App\Http\Controllers\Web\Admin\AdminSiteSettingsController;
use App\Http\Controllers\Web\Admin\AdminUsersController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/users', [AdminUsersController::class, 'index'])->name('users');
    Route::post('/users', [AdminUsersController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminUsersController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUsersController::class, 'destroy'])->name('users.destroy');
    Route::get('/integrations', [AdminIntegrationsController::class, 'index'])->name('integrations');
    Route::post('/integrations/save', [AdminIntegrationsController::class, 'update'])->name('integrations.update');
    Route::post('/integrations/{integration}/test', [AdminIntegrationsController::class, 'test'])->name('integrations.test');
    Route::post('/integrations/alatpay/sync-deposits', [AdminIntegrationsController::class, 'syncStaticDeposits'])->name('integrations.alatpay.sync');
    Route::get('/platform', [AdminPlatformSettingsController::class, 'index'])->name('platform');
    Route::put('/platform', [AdminPlatformSettingsController::class, 'update'])->name('platform.update');
    Route::get('/app-settings', [AdminAppSettingsController::class, 'index'])->name('app');
    Route::put('/app-settings', [AdminAppSettingsController::class, 'update'])->name('app.update');
    Route::get('/site', [AdminSiteSettingsController::class, 'index'])->name('site');
    Route::put('/site', [AdminSiteSettingsController::class, 'update'])->name('site.update');
    Route::post('/site/test-mail', [AdminSiteSettingsController::class, 'testMail'])->name('site.test-mail');
});
