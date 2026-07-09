<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\AdminAppSettingsController;
use App\Http\Controllers\Web\Admin\AdminDashboardController;
use App\Http\Controllers\Web\Admin\AdminIntegrationsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
Route::get('/integrations', [AdminIntegrationsController::class, 'index'])->name('integrations');
Route::post('/integrations/save', [AdminIntegrationsController::class, 'update'])->name('integrations.update');
Route::post('/integrations/{integration}/test', [AdminIntegrationsController::class, 'test'])->name('integrations.test');
Route::get('/app-settings', [AdminAppSettingsController::class, 'index'])->name('app');
Route::put('/app-settings', [AdminAppSettingsController::class, 'update'])->name('app.update');
