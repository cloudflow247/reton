<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PinController;
use App\Http\Controllers\Api\V1\Callback\CallbackController;
use App\Http\Controllers\Api\V1\Payment\AlatpayWebhookController;
use App\Http\Controllers\Api\V1\Payment\DepositController;
use App\Http\Controllers\Api\V1\Payment\PaymentRequestController;
use App\Http\Controllers\Api\V1\Payment\PayoutController;
use App\Http\Controllers\Api\V1\Recovery\RecoveryController;
use App\Http\Controllers\Api\V1\Transfer\TransferController;
use App\Http\Controllers\Api\V1\Wallet\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
*/

// Public webhook receiver — authenticated by HMAC signature, not Sanctum.
Route::post('webhooks/alatpay', [AlatpayWebhookController::class, 'handle'])->name('webhooks.alatpay');

// Public payer-facing details for a payment request (no auth).
Route::get('pay/{reference}', [PaymentRequestController::class, 'publicShow'])->name('pay.show');

Route::middleware('auth:sanctum')->prefix('payment-requests')->name('payment-requests.')->group(function (): void {
    Route::get('/', [PaymentRequestController::class, 'index'])->name('index');
    Route::post('/', [PaymentRequestController::class, 'store'])->name('store');
    Route::get('{paymentRequest}', [PaymentRequestController::class, 'show'])->name('show');
    Route::post('{paymentRequest}/cancel', [PaymentRequestController::class, 'cancel'])->name('cancel');
});

Route::middleware('auth:sanctum')->prefix('deposits')->name('deposits.')->group(function (): void {
    Route::get('/', [DepositController::class, 'index'])->name('index');
    Route::post('/', [DepositController::class, 'store'])->name('store');
    Route::get('{deposit}', [DepositController::class, 'show'])->name('show');
});

Route::middleware('auth:sanctum')->prefix('payouts')->name('payouts.')->group(function (): void {
    Route::get('/', [PayoutController::class, 'index'])->name('index');
    Route::post('/', [PayoutController::class, 'store'])->name('store');
    Route::get('{payout}', [PayoutController::class, 'show'])->name('show');
});

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::post('pin', [PinController::class, 'set'])->name('pin.set');
        Route::post('pin/verify', [PinController::class, 'verify'])->name('pin.verify');
    });
});

Route::middleware('auth:sanctum')->prefix('wallets')->name('wallets.')->group(function (): void {
    Route::get('/', [WalletController::class, 'index'])->name('index');
    Route::get('lookup', [WalletController::class, 'lookup'])->name('lookup');
    Route::get('{wallet}', [WalletController::class, 'show'])->name('show');
    Route::get('{wallet}/transactions', [WalletController::class, 'transactions'])->name('transactions');
    Route::post('{wallet}/transfer', [WalletController::class, 'transfer'])->name('transfer');
    Route::post('{wallet}/withdraw', [WalletController::class, 'withdraw'])->name('withdraw');
});

Route::middleware('auth:sanctum')->prefix('transfers')->name('transfers.')->group(function (): void {
    Route::get('/', [TransferController::class, 'index'])->name('index');
    Route::post('/', [TransferController::class, 'store'])->name('store');
    Route::get('{transfer}', [TransferController::class, 'show'])->name('show');
    Route::post('{transfer}/release', [TransferController::class, 'release'])->name('release');
    Route::post('{transfer}/callbacks', [CallbackController::class, 'store'])->name('callbacks.store');
    Route::post('{transfer}/recoveries', [RecoveryController::class, 'store'])->name('recoveries.store');
});

Route::middleware('auth:sanctum')->prefix('recoveries')->name('recoveries.')->group(function (): void {
    Route::get('/', [RecoveryController::class, 'index'])->name('index');
    Route::get('{recovery}', [RecoveryController::class, 'show'])->name('show');
    Route::post('{recovery}/return', [RecoveryController::class, 'return'])->name('return');
    Route::post('{recovery}/dispute', [RecoveryController::class, 'dispute'])->name('dispute');
    Route::post('{recovery}/evidence', [RecoveryController::class, 'evidence'])->name('evidence');
});

Route::middleware('auth:sanctum')->prefix('callbacks')->name('callbacks.')->group(function (): void {
    Route::get('/', [CallbackController::class, 'index'])->name('index');
    Route::get('{callback}', [CallbackController::class, 'show'])->name('show');
    Route::post('{callback}/accept', [CallbackController::class, 'accept'])->name('accept');
    Route::post('{callback}/reject', [CallbackController::class, 'reject'])->name('reject');
    Route::post('{callback}/evidence', [CallbackController::class, 'evidence'])->name('evidence');
});
