<?php

declare(strict_types=1);

use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Enums\PaymentRequestStatus;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Services\PaymentRequestService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('credits a pending request that AlatPay reports as paid', function () {
    $gateway = new FakeAlatpayGateway();
    $this->app->instance(AlatpayGateway::class, $gateway);

    $user = User::factory()->create();
    $wallet = app(WalletService::class)->open($user, 'NGN');
    $request = app(PaymentRequestService::class)->create($user, $wallet, Money::of(250_00, 'NGN'), 'Lunch');

    // Webhook never arrived, but AlatPay received the money.
    $gateway->markPaid($request->provider_reference, 25000);
    PaymentRequest::whereKey($request->id)->update(['created_at' => now()->subMinutes(10)]);

    $this->artisan('payment-requests:reconcile')->assertExitCode(0);

    expect($request->fresh()->status)->toBe(PaymentRequestStatus::Paid)
        ->and($wallet->fresh()->balance)->toBe(25000);
});
