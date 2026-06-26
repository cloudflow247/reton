<?php

declare(strict_types=1);

use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a payment request with enum status and relations', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $request = PaymentRequest::create([
        'reference' => 'REQ-TEST1',
        'requester_user_id' => $user->getKey(),
        'wallet_id' => $wallet->getKey(),
        'provider' => 'alatpay',
        'status' => PaymentRequestStatus::Pending,
        'amount' => 250_00,
        'currency' => 'NGN',
        'title' => 'Lunch money',
    ]);

    expect($request->status)->toBe(PaymentRequestStatus::Pending)
        ->and($request->isOpen())->toBeTrue()
        ->and($request->isPaid())->toBeFalse()
        ->and($request->amount)->toBe(25000)
        ->and($request->requester->is($user))->toBeTrue()
        ->and($request->wallet->is($wallet))->toBeTrue();
});
