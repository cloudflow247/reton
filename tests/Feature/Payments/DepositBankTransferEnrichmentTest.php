<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\Transaction;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\DepositStatus;
use App\Domain\Payments\Services\AlatpayDepositService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gateway = new FakeAlatpayGateway;
    $this->app->instance(AlatpayGateway::class, $this->gateway);
});

it('enriches deposits from fetchTransaction on reconcile', function () {
    $user = User::factory()->create();
    ensureVerifiedBvn($user);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $deposit = app(AlatpayDepositService::class)->initiate($user, $wallet, Money::of(500_00, 'NGN'));
    $this->gateway->markPaid((string) $deposit->provider_reference, 500_00, 'NGN', [
        'narration' => 'NIP/FGN/ADA LOVELACE/Reton top up',
        'payer_name' => 'Ada Lovelace',
        'bank_name' => 'Guaranty Trust Bank',
    ]);

    expect(app(AlatpayDepositService::class)->reconcile($deposit->fresh()))->toBeTrue();

    $deposit->refresh();
    $txn = Transaction::find($deposit->transaction_id);

    expect($deposit->status)->toBe(DepositStatus::Completed)
        ->and($deposit->metadata['bank_transfer']['narration'] ?? null)->toBe('NIP/FGN/ADA LOVELACE/Reton top up')
        ->and($deposit->metadata['bank_transfer']['payer_name'] ?? null)->toBe('Ada Lovelace')
        ->and($deposit->metadata['bank_transfer']['bank_name'] ?? null)->toBe('Guaranty Trust Bank')
        ->and($txn?->description)->toContain('NIP/FGN/ADA LOVELACE/Reton top up');
});

it('exposes bank transfer details on the deposit resource after reconcile', function () {
    $user = User::factory()->create();
    ensureVerifiedBvn($user);
    $wallet = app(WalletService::class)->open($user, 'NGN');

    $deposit = app(AlatpayDepositService::class)->initiate($user, $wallet, Money::of(250_00, 'NGN'));
    $this->gateway->markPaid((string) $deposit->provider_reference, 250_00, 'NGN', [
        'narration' => 'Transfer from Access Bank',
        'payer_name' => 'Chioma Okeke',
        'bank_name' => 'Access Bank',
    ]);
    app(AlatpayDepositService::class)->reconcile($deposit->fresh());

    $this->actingAs($user)
        ->getJson('/api/v1/deposits/'.$deposit->fresh()->id)
        ->assertOk()
        ->assertJsonPath('data.bank_transfer.narration', 'Transfer from Access Bank')
        ->assertJsonPath('data.bank_transfer.payer_name', 'Chioma Okeke')
        ->assertJsonPath('data.provider_reference', $deposit->provider_reference);
});
