<?php

declare(strict_types=1);

use App\Domain\Payments\Enums\StaticAccountStatus;
use App\Domain\Payments\Enums\StaticWalletType;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists a static account with enum casts and relations', function () {
    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $account = StaticAccount::create([
        'wallet_id' => $wallet->getKey(),
        'user_id' => $user->getKey(),
        'provider' => 'alatpay',
        'wallet_type' => StaticWalletType::Collection,
        'status' => StaticAccountStatus::PendingOtp,
    ]);

    expect($account->wallet_type)->toBe(StaticWalletType::Collection)
        ->and($account->wallet_type->providerCode())->toBe(2)
        ->and($account->status)->toBe(StaticAccountStatus::PendingOtp)
        ->and($account->isActive())->toBeFalse()
        ->and($account->wallet->is($wallet))->toBeTrue()
        ->and($account->user->is($user))->toBeTrue();
});

it('maps the individual wallet type to provider code 1', function () {
    expect(StaticWalletType::Individual->providerCode())->toBe(1);
});
