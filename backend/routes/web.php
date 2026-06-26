<?php

declare(strict_types=1);

use App\Http\Controllers\Web\ActivityController;
use App\Http\Controllers\Web\AddMoneyController;
use App\Http\Controllers\Web\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Web\Auth\RegisteredUserController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PinController;
use App\Http\Controllers\Web\ProtectionController;
use App\Http\Controllers\Web\SendController;
use App\Http\Controllers\Web\WalletLookupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public marketing pages
|--------------------------------------------------------------------------
| Authenticated visitors are bounced to their dashboard so "/" stays the
| marketing home for signed-out users only.
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : Inertia::render('Public/Home');
})->name('home');

Route::inertia('/security', 'Public/Security')->name('security');
Route::inertia('/how-it-works', 'Public/HowItWorks')->name('how-it-works');

/*
|--------------------------------------------------------------------------
| Guest auth
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Authenticated app (Inertia)
|--------------------------------------------------------------------------
| GET screens that only need the shared `auth` prop render directly; data and
| mutations route through Web controllers that reuse the domain services and
| the existing API FormRequests.
*/
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Send + name enquiry + transfer
    Route::inertia('/send', 'Send')->name('send');
    Route::get('/lookup', [WalletLookupController::class, 'show'])->name('wallets.lookup');
    Route::post('/transfers', [SendController::class, 'store'])->name('transfers.store');

    // Add money (deposit) + receive
    Route::inertia('/add-money', 'AddMoney')->name('add-money');
    Route::post('/deposits', [AddMoneyController::class, 'store'])->name('deposits.store');
    Route::inertia('/receive', 'Receive')->name('receive');

    // Activity + profile
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity');
    Route::inertia('/profile', 'Profile')->name('profile');

    // Transaction PIN
    Route::inertia('/pin', 'SetPin')->name('pin');
    Route::post('/pin', [PinController::class, 'update'])->name('pin.update');

    // Protection center — held transfers, callbacks, recoveries
    Route::get('/protection', [ProtectionController::class, 'index'])->name('protection');
    Route::post('/transfers/{transfer}/release', [ProtectionController::class, 'release'])->name('transfers.release');
    Route::post('/transfers/{transfer}/callbacks', [ProtectionController::class, 'storeCallback'])->name('callbacks.store');
    Route::post('/callbacks/{callback}/accept', [ProtectionController::class, 'acceptCallback'])->name('callbacks.accept');
    Route::post('/callbacks/{callback}/reject', [ProtectionController::class, 'rejectCallback'])->name('callbacks.reject');
    Route::post('/transfers/{transfer}/recoveries', [ProtectionController::class, 'storeRecovery'])->name('recoveries.store');
    Route::post('/recoveries/{recovery}/return', [ProtectionController::class, 'returnRecovery'])->name('recoveries.return');
    Route::post('/recoveries/{recovery}/dispute', [ProtectionController::class, 'disputeRecovery'])->name('recoveries.dispute');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
