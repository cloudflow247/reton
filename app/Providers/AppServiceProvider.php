<?php

namespace App\Providers;

use App\Domain\Bills\Models\BillPayment;
use App\Domain\Bills\Policies\BillPaymentPolicy;
use App\Domain\Bills\Remita\Contracts\BillProviderGateway;
use App\Domain\Bills\Remita\Gateways\FakeBillProvider;
use App\Domain\Bills\Remita\Gateways\HttpRemitaProvider;
use App\Domain\Bills\Services\BillPaymentService;
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
use App\Domain\Marketplace\Models\DigitalListing;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Marketplace\Policies\DigitalListingPolicy;
use App\Domain\Marketplace\Policies\DigitalOrderPolicy;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Policies\DepositPolicy;
use App\Domain\Payments\Policies\PaymentRequestPolicy;
use App\Domain\Payments\Policies\PayoutPolicy;
use App\Domain\Payments\Policies\StaticAccountPolicy;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Recovery\Policies\RecoveryPolicy;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Policies\TransferPolicy;
use App\Domain\Transfers\Services\TransferService;
use App\Observers\TransferMarketplaceObserver;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Policies\WalletPolicy;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Http\Resources\Json\JsonResource;
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
        $this->app->singleton(DigitalMarketplaceService::class);
        $this->app->singleton(CallbackService::class);
        $this->app->singleton(RecoveryService::class);
        $this->app->singleton(BillPaymentService::class);

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

        // Bill-payment provider (Remita): live HTTP integration by default, an
        // in-memory fake for local/testing. Callers depend only on the interface.
        $this->app->singleton(BillProviderGateway::class, fn () => config('services.remita.driver') === 'fake'
            ? new FakeBillProvider
            : new HttpRemitaProvider);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Inertia serializes API Resources via toResponse(), which would wrap
        // every resource prop in a "data" key (auth.user.data, props.transfers.data…).
        // Drop the wrapper so pages read bare arrays. The API envelope nests
        // resources, so its JSON shape is unaffected.
        JsonResource::withoutWrapping();

        // Wallet lives in a domain namespace, so register its policy explicitly
        // rather than relying on convention-based discovery.
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(Transfer::class, TransferPolicy::class);
        Gate::policy(DigitalListing::class, DigitalListingPolicy::class);
        Gate::policy(DigitalOrder::class, DigitalOrderPolicy::class);
        Gate::policy(Callback::class, CallbackPolicy::class);
        Gate::policy(Recovery::class, RecoveryPolicy::class);
        Gate::policy(Deposit::class, DepositPolicy::class);
        Gate::policy(Payout::class, PayoutPolicy::class);
        Gate::policy(PaymentRequest::class, PaymentRequestPolicy::class);
        Gate::policy(StaticAccount::class, StaticAccountPolicy::class);
        Gate::policy(BillPayment::class, BillPaymentPolicy::class);

        Transfer::observe(TransferMarketplaceObserver::class);
    }
}
