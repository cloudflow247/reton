<?php

namespace App\Providers;

use App\Domain\Callback\Models\Callback;
use App\Domain\Callback\Policies\CallbackPolicy;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Rules\FailedPinRule;
use App\Domain\Fraud\Rules\LargeAmountRule;
use App\Domain\Fraud\Rules\NewBeneficiaryRule;
use App\Domain\Fraud\Rules\NewDeviceRule;
use App\Domain\Fraud\Rules\VelocityRule;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Fraud\Services\RuleBasedFraudScorer;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Policies\DepositPolicy;
use App\Domain\Payments\Policies\PaymentRequestPolicy;
use App\Domain\Payments\Policies\PayoutPolicy;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Recovery\Policies\RecoveryPolicy;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Policies\TransferPolicy;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Policies\WalletPolicy;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The ledger is a process-wide singleton: the single authority through
        // which every balance mutation in Reton must flow.
        $this->app->singleton(LedgerService::class);
        $this->app->singleton(SystemAccountResolver::class);
        $this->app->singleton(WalletService::class);
        $this->app->singleton(TransferService::class);
        $this->app->singleton(CallbackService::class);
        $this->app->singleton(RecoveryService::class);

        // The fraud scorer is the seam a future Go/gRPC scorer binds into. The
        // rule order does not affect the score (points are summed).
        $this->app->singleton(FraudScorer::class, fn ($app) => new RuleBasedFraudScorer([
            $app->make(VelocityRule::class),
            $app->make(LargeAmountRule::class),
            $app->make(NewDeviceRule::class),
            $app->make(FailedPinRule::class),
            $app->make(NewBeneficiaryRule::class),
        ]));
        $this->app->singleton(FraudService::class);

        // AlatPay gateway: live HTTP integration by default, in-memory fake for
        // local/testing. The rest of the app depends only on the interface.
        $this->app->singleton(AlatpayGateway::class, fn () => config('services.alatpay.driver') === 'fake'
            ? new FakeAlatpayGateway
            : new HttpAlatpayGateway);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Wallet lives in a domain namespace, so register its policy explicitly
        // rather than relying on convention-based discovery.
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(Transfer::class, TransferPolicy::class);
        Gate::policy(Callback::class, CallbackPolicy::class);
        Gate::policy(Recovery::class, RecoveryPolicy::class);
        Gate::policy(Deposit::class, DepositPolicy::class);
        Gate::policy(Payout::class, PayoutPolicy::class);
        Gate::policy(PaymentRequest::class, PaymentRequestPolicy::class);
    }
}
