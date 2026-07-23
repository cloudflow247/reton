<?php

namespace App\Providers;

use App\Domain\Bills\Interswitch\Gateways\HttpInterswitchProvider;
use App\Domain\Bills\Models\BillPayment;
use App\Domain\Bills\Policies\BillPaymentPolicy;
use App\Domain\Bills\Remita\Contracts\BillProviderGateway;
use App\Domain\Bills\Remita\Gateways\FakeBillProvider;
use App\Domain\Bills\Remita\Gateways\HttpRemitaProvider;
use App\Domain\Bills\Services\BillPaymentService;
use App\Domain\Callback\Models\Callback;
use App\Domain\Callback\Policies\CallbackPolicy;
use App\Domain\Callback\Services\CallbackService;
use App\Domain\Cards\Bridgecard\Gateways\FakeBridgecardVirtualCardGateway;
use App\Domain\Cards\Bridgecard\Gateways\HttpBridgecardVirtualCardGateway;
use App\Domain\Cards\Contracts\VirtualCardGateway;
use App\Domain\Cards\Models\VirtualCard;
use App\Domain\Cards\Policies\VirtualCardPolicy;
use App\Domain\Cards\Services\CardFundingService;
use App\Domain\Cards\Services\FxQuoteService;
use App\Domain\Cards\Services\VirtualCardService;
use App\Domain\Fraud\Contracts\FraudScorer;
use App\Domain\Fraud\Rules\FailedPinRule;
use App\Domain\Fraud\Rules\LargeAmountRule;
use App\Domain\Fraud\Rules\NewBeneficiaryRule;
use App\Domain\Fraud\Rules\NewDeviceRule;
use App\Domain\Fraud\Rules\VelocityRule;
use App\Domain\Fraud\Services\FraudService;
use App\Domain\Fraud\Services\RuleBasedFraudScorer;
use App\Domain\Kyc\Contracts\KycVerificationGateway;
use App\Domain\Kyc\Gateways\FakeDojahGateway;
use App\Domain\Kyc\Gateways\HttpDojahGateway;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Ledger\Services\SystemAccountResolver;
use App\Domain\Logistics\Giglogistics\Contracts\GiglogisticsGateway;
use App\Domain\Logistics\Giglogistics\Gateways\FakeGiglogisticsGateway;
use App\Domain\Logistics\Giglogistics\Services\GiglogisticsWebhookService;
use App\Domain\Marketplace\Models\DigitalListing;
use App\Domain\Marketplace\Models\DigitalOrder;
use App\Domain\Marketplace\Policies\DigitalListingPolicy;
use App\Domain\Marketplace\Policies\DigitalOrderPolicy;
use App\Domain\Marketplace\Services\DigitalMarketplaceService;
use App\Domain\Marketplace\Services\HubVerificationService;
use App\Domain\Marketplace\Services\ListingVerificationService;
use App\Domain\Marketplace\Services\ShipmentService;
use App\Domain\Notifications\Contracts\SmsGateway;
use App\Domain\Notifications\Gateways\FakeTermiiGateway;
use App\Domain\Notifications\Gateways\HttpTermiiGateway;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\HttpAlatpayGateway;
use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Gateways\AlatpayPayoutGateway;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Payments\Models\PaymentRequest;
use App\Domain\Payments\Models\Payout;
use App\Domain\Payments\Models\StaticAccount;
use App\Domain\Payments\Paystack\Gateways\FakePaystackPayoutGateway;
use App\Domain\Payments\Paystack\Gateways\HttpPaystackPayoutGateway;
use App\Domain\Payments\Policies\DepositPolicy;
use App\Domain\Payments\Policies\PaymentRequestPolicy;
use App\Domain\Payments\Policies\PayoutPolicy;
use App\Domain\Payments\Policies\StaticAccountPolicy;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Recovery\Policies\RecoveryPolicy;
use App\Domain\Recovery\Services\RecoveryService;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Transfers\Policies\TransferPolicy;
use App\Domain\Transfers\Services\TransferService;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Policies\WalletPolicy;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Observers\TransferMarketplaceObserver;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->singleton(ListingVerificationService::class);
        $this->app->singleton(HubVerificationService::class);
        $this->app->singleton(ShipmentService::class);
        $this->app->singleton(GiglogisticsWebhookService::class);
        $this->app->singleton(CallbackService::class);
        $this->app->singleton(RecoveryService::class);
        $this->app->singleton(BillPaymentService::class);
        $this->app->singleton(VirtualCardService::class);
        $this->app->singleton(FxQuoteService::class);
        $this->app->singleton(CardFundingService::class);

        $this->app->singleton(VirtualCardGateway::class, fn ($app) => config('services.bridgecard.driver') === 'fake'
            ? new FakeBridgecardVirtualCardGateway
            : $app->make(HttpBridgecardVirtualCardGateway::class));

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

        // Same fake instance as AlatpayGateway when driver=fake (demo simulate-pay).
        $this->app->singleton(FakeAlatpayGateway::class, function ($app): FakeAlatpayGateway {
            $gateway = $app->make(AlatpayGateway::class);

            if (! $gateway instanceof FakeAlatpayGateway) {
                throw new \RuntimeException('Fake ALATPay gateway is not active.');
            }

            return $gateway;
        });

        // Outbound payouts: Paystack Transfers (default) or ALATPay Debit Wallet adapter.
        $this->app->singleton(PayoutGateway::class, function ($app) {
            $provider = (string) config('reton.payouts.provider', 'paystack');

            if ($provider === 'alatpay') {
                return new AlatpayPayoutGateway($app->make(AlatpayGateway::class));
            }

            return config('services.paystack.driver') === 'fake'
                ? new FakePaystackPayoutGateway
                : $app->make(HttpPaystackPayoutGateway::class);
        });

        // Bill payments: Interswitch Quickteller VAS (live) or in-memory fake for tests.
        $this->app->singleton(BillProviderGateway::class, function ($app) {
            $provider = config('reton.bills.provider', 'interswitch');

            if ($provider === 'interswitch') {
                return config('services.interswitch.driver') === 'fake'
                    ? new FakeBillProvider
                    : $app->make(HttpInterswitchProvider::class);
            }

            return config('services.remita.driver') === 'fake'
                ? new FakeBillProvider
                : new HttpRemitaProvider;
        });

        $this->app->singleton(GiglogisticsGateway::class, fn () => new FakeGiglogisticsGateway(
            app(HubVerificationService::class),
        ));

        $this->app->singleton(KycVerificationGateway::class, fn () => config('services.dojah.driver') === 'http'
            ? app(HttpDojahGateway::class)
            : new FakeDojahGateway);

        $this->app->singleton(SmsGateway::class, fn () => config('services.termii.driver') === 'http'
            ? app(HttpTermiiGateway::class)
            : new FakeTermiiGateway);
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
        Gate::policy(User::class, UserPolicy::class);
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
        Gate::policy(VirtualCard::class, VirtualCardPolicy::class);

        Transfer::observe(TransferMarketplaceObserver::class);

        RateLimiter::for('auth', function (Request $request) {
            $limit = max(3, (int) config('reton.security.auth_rate_limit', 10));

            return Limit::perMinute($limit)->by($request->ip());
        });

        $this->app->booted(function (): void {
            app(PlatformSettingsService::class)->applyToConfig();
            config(['session.secure' => (bool) config('reton.security.session_secure_cookie', false)]);
        });
    }
}
