<?php

declare(strict_types=1);

use App\Domain\Notifications\Contracts\SmsGateway;
use App\Domain\Notifications\Gateways\FakeTermiiGateway;
use App\Mail\WalletTransactionMail;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'reton.mail.notifications_enabled' => true,
        'reton.sms.notifications_enabled' => true,
        'reton.sms.alert_fee_minor' => 600,
        'services.termii.driver' => 'fake',
    ]);

    $this->app->instance(SmsGateway::class, new FakeTermiiGateway);
});

it('emails a reton credit alert when the wallet is funded', function () {
    Mail::fake();

    [$user, $wallet] = readyUserWithWallet(['notify_email' => true, 'notify_sms' => false]);

    app(\App\Domain\Wallet\Services\WalletService::class)
        ->fund($wallet, Money::of(5_000, 'NGN'), 'fund-alert-1', [], 'Bank deposit');

    Mail::assertQueued(WalletTransactionMail::class, function (WalletTransactionMail $mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->direction === 'credit'
            && $mail->amount->amount === 5_000;
    });
});

it('does not email when email alerts are disabled', function () {
    Mail::fake();

    [$user, $wallet] = readyUserWithWallet(['notify_email' => false, 'notify_sms' => false]);

    app(\App\Domain\Wallet\Services\WalletService::class)
        ->fund($wallet, Money::of(5_000, 'NGN'), 'fund-alert-off');

    Mail::assertNotQueued(WalletTransactionMail::class);
});

it('sends an sms alert and charges the sms fee when sms alerts are enabled', function () {
    Mail::fake();

    [$user, $wallet] = readyUserWithWallet(['notify_email' => false, 'notify_sms' => false], fundMinor: 10_000);
    $user->forceFill(['notify_sms' => true])->save();

    app(\App\Domain\Wallet\Services\WalletService::class)
        ->fund($wallet, Money::of(2_000, 'NGN'), 'fund-sms-1');

    expect((int) $wallet->fresh()->balance)->toBe(10_000 + 2_000 - 600);
});

it('skips sms when the wallet cannot cover the sms fee', function () {
    Mail::fake();

    [$user, $wallet] = readyUserWithWallet(['notify_sms' => true, 'notify_email' => false], fundMinor: 0);

    app(\App\Domain\Wallet\Services\WalletService::class)
        ->fund($wallet, Money::of(100, 'NGN'), 'fund-sms-broke');

    expect((int) $wallet->fresh()->balance)->toBe(100);
});
