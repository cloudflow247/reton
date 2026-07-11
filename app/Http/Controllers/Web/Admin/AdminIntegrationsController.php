<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Bills\Interswitch\Gateways\HttpInterswitchProvider;
use App\Domain\Bills\Interswitch\Services\InterswitchTokenService;
use App\Domain\Cards\Bridgecard\Gateways\HttpBridgecardVirtualCardGateway;
use App\Domain\Notifications\Contracts\SmsGateway;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Contracts\PayoutGateway;
use App\Domain\Payments\Services\StaticAccountService;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminIntegrationsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly AlatpayGateway $alatpay,
        private readonly PayoutGateway $payouts,
        private readonly StaticAccountService $staticAccounts,
        private readonly HttpInterswitchProvider $interswitch,
        private readonly HttpBridgecardVirtualCardGateway $bridgecard,
        private readonly InterswitchTokenService $interswitchTokens,
        private readonly SmsGateway $sms,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Integrations', [
            'integrations' => [
                'alatpay' => array_merge(
                    $this->settings->maskedGroup('alatpay'),
                    ['ready' => $this->settings->isIntegrationReady('alatpay')],
                ),
                'paystack' => array_merge(
                    $this->settings->maskedGroup('paystack'),
                    ['ready' => $this->settings->isIntegrationReady('paystack')],
                ),
                'interswitch' => array_merge(
                    $this->settings->maskedGroup('interswitch'),
                    ['ready' => $this->settings->isIntegrationReady('interswitch')],
                ),
                'bridgecard' => array_merge(
                    $this->settings->maskedGroup('bridgecard'),
                    ['ready' => $this->settings->isVirtualCardsReady()],
                ),
                'giglogistics' => array_merge(
                    $this->settings->maskedGroup('giglogistics'),
                    ['ready' => $this->settings->isIntegrationReady('giglogistics')],
                ),
                'dojah' => array_merge(
                    $this->settings->maskedGroup('dojah'),
                    ['ready' => $this->settings->isDojahReady()],
                ),
                'remita' => array_merge(
                    $this->settings->maskedGroup('remita'),
                    ['ready' => $this->settings->isRemitaReady()],
                ),
                'termii' => array_merge(
                    $this->settings->maskedGroup('termii'),
                    ['ready' => $this->settings->isTermiiReady()],
                ),
            ],
            'webhookUrls' => [
                'alatpay' => url('/api/v1/webhooks/alatpay'),
                'paystack' => url('/api/v1/webhooks/paystack'),
                'giglogistics' => url('/api/v1/webhooks/giglogistics'),
            ],
            'docsUrls' => [
                'paystack' => 'https://paystack.com/docs/transfers/single-transfers/',
                'interswitch' => 'https://docs.interswitchgroup.com/docs/bills-payment-1',
                'bridgecard' => 'https://docs.bridgecard.co/',
                'dojah' => 'https://docs.dojah.io',
                'remita' => 'https://remita.net',
                'termii' => 'https://developers.termii.com/',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'integration' => ['required', 'in:alatpay,paystack,interswitch,bridgecard,giglogistics,dojah,remita,termii'],
        ]);

        $group = (string) $request->input('integration');

        $rules = match ($group) {
            'alatpay' => [
                'driver' => ['required', 'in:fake,http'],
                'base_url' => ['required', 'url', 'max:255'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'merchant_email' => ['nullable', 'email', 'max:255'],
                'merchant_password' => ['nullable', 'string', 'max:500'],
                'business_id' => ['nullable', 'string', 'max:120'],
                'business_bvn' => ['nullable', 'string', 'max:20'],
                'webhook_secret' => ['nullable', 'string', 'max:500'],
                'timeout' => ['required', 'integer', 'min:5', 'max:120'],
            ],
            'paystack' => [
                'driver' => ['required', 'in:fake,http'],
                'base_url' => ['required', 'url', 'max:255'],
                'secret_key' => ['nullable', 'string', 'max:500'],
                'public_key' => ['nullable', 'string', 'max:500'],
                'webhook_secret' => ['nullable', 'string', 'max:500'],
                'timeout' => ['required', 'integer', 'min:5', 'max:120'],
            ],
            'interswitch' => [
                'driver' => ['required', 'in:fake,http'],
                'passport_url' => ['required', 'url', 'max:255'],
                'base_url' => ['required', 'url', 'max:255'],
                'terminal_id' => ['nullable', 'string', 'max:64'],
                'client_id' => ['nullable', 'string', 'max:500'],
                'client_secret' => ['nullable', 'string', 'max:500'],
                'request_reference_prefix' => ['required', 'string', 'max:8'],
                'timeout' => ['required', 'integer', 'min:5', 'max:120'],
            ],
            'bridgecard' => [
                'driver' => ['required', 'in:fake,http'],
                'base_url' => ['required', 'url', 'max:255'],
                'access_token' => ['nullable', 'string', 'max:500'],
                'secret_key' => ['nullable', 'string', 'max:500'],
                'timeout' => ['required', 'integer', 'min:5', 'max:120'],
            ],
            'giglogistics' => [
                'driver' => ['required', 'in:fake,http'],
                'base_url' => ['required', 'url', 'max:255'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'webhook_secret' => ['nullable', 'string', 'max:500'],
                'fake_advance_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            ],
            'dojah' => [
                'driver' => ['required', 'in:fake,http'],
                'base_url' => ['required', 'url', 'max:255'],
                'app_id' => ['nullable', 'string', 'max:500'],
                'secret_key' => ['nullable', 'string', 'max:500'],
                'timeout' => ['required', 'integer', 'min:5', 'max:120'],
            ],
            'remita' => [
                'driver' => ['required', 'in:fake,http'],
                'base_url' => ['required', 'url', 'max:255'],
                'merchant_id' => ['nullable', 'string', 'max:120'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'api_secret' => ['nullable', 'string', 'max:500'],
                'timeout' => ['required', 'integer', 'min:5', 'max:120'],
            ],
            'termii' => [
                'driver' => ['required', 'in:fake,http'],
                'base_url' => ['required', 'url', 'max:255'],
                'api_key' => ['nullable', 'string', 'max:500'],
                'sender_id' => ['required', 'string', 'max:20'],
                'channel' => ['required', 'string', 'max:32'],
                'timeout' => ['required', 'integer', 'min:5', 'max:120'],
            ],
        };

        $validated = $request->validate(array_merge(
            ['integration' => ['required', 'in:alatpay,paystack,interswitch,bridgecard,giglogistics,dojah,remita,termii']],
            $rules,
        ));

        unset($validated['integration']);

        $this->settings->updateGroup($group, $validated, $request->user(), $request->ip());

        if ($group === 'interswitch') {
            $this->interswitchTokens->bustCache();
        }

        return back()->with('success', ucfirst($group).' settings saved and applied.');
    }

    public function test(Request $request, string $adminPrefix, string $integration): RedirectResponse
    {
        unset($adminPrefix);

        if ($integration === 'alatpay') {
            if (! $this->settings->isIntegrationReady('alatpay')) {
                return back()->with('error', 'Add merchant email, password, Business ID, and Business BVN before testing.');
            }

            if (config('services.alatpay.driver') === 'fake') {
                return back()->with('success', 'ALATPay is in demo (fake) mode — switch driver to Live HTTP to test the real API.');
            }

            try {
                // Must hit Static Wallet — bank-transfer 404 was a false "credentials OK".
                $this->alatpay->pingStaticWallet();

                return back()->with('success', 'ALATPay merchant login + Static Wallet API reachable.');
            } catch (\Throwable $e) {
                return back()->with('error', 'ALATPay Static Wallet test failed: '.$e->getMessage());
            }
        }

        if ($integration === 'paystack') {
            if (! $this->settings->isIntegrationReady('paystack')) {
                return back()->with('error', 'Add a Paystack secret key (or switch driver to demo) before testing.');
            }

            if (config('services.paystack.driver') === 'fake') {
                return back()->with('success', 'Paystack is in demo (fake) mode — switch driver to Live HTTP to test Transfers.');
            }

            try {
                $this->payouts->ping();

                return back()->with('success', 'Paystack Transfers API reachable — withdrawals ready.');
            } catch (\Throwable $e) {
                return back()->with('error', 'Paystack test failed: '.$e->getMessage());
            }
        }

        if ($integration === 'interswitch') {
            if (! $this->settings->isIntegrationReady('interswitch')) {
                return back()->with('error', 'Add Client ID, Client Secret, and Terminal ID before testing.');
            }

            if (config('services.interswitch.driver') === 'fake') {
                return back()->with('success', 'Interswitch is in demo (fake) mode — switch driver to Live HTTP for production bill APIs.');
            }

            try {
                if ($this->interswitch->ping()) {
                    return back()->with('success', 'Quickteller VAS reachable for bill payments.');
                }
            } catch (\Throwable $e) {
                return back()->with('error', 'Quickteller VAS failed: '.$e->getMessage());
            }

            return back()->with('error', 'Interswitch bill credentials incomplete.');
        }

        if ($integration === 'bridgecard') {
            if (! $this->settings->isVirtualCardsReady()) {
                return back()->with('error', 'Add Bridgecard access token and secret key before testing.');
            }

            if (config('services.bridgecard.driver') === 'fake') {
                return back()->with('success', 'Bridgecard is in demo (fake) mode — switch driver to Live HTTP for production card issuing.');
            }

            try {
                if ($this->bridgecard->ping()) {
                    return back()->with('success', 'Bridgecard credentials configured.');
                }
            } catch (\Throwable $e) {
                return back()->with('error', 'Bridgecard test failed: '.$e->getMessage());
            }

            return back()->with('error', 'Bridgecard credentials incomplete.');
        }

        if ($integration === 'termii') {
            if (! $this->settings->isTermiiReady()) {
                return back()->with('error', 'Add Termii API key and sender ID before testing.');
            }

            if (config('services.termii.driver') === 'fake') {
                return back()->with('success', 'Termii is in demo (fake) mode — switch driver to Live HTTP for production SMS.');
            }

            if ($this->sms->ping()) {
                return back()->with('success', 'Termii API reachable — SMS & WhatsApp OTP ready.');
            }

            return back()->with('error', 'Termii test failed — check API key and sender ID.');
        }

        if ($integration === 'dojah') {
            if (! $this->settings->isDojahReady()) {
                return back()->with('error', 'Add Dojah App ID and Secret Key before testing.');
            }

            if (config('services.dojah.driver') === 'fake') {
                return back()->with('success', 'Dojah is in demo (fake) mode — switch driver to Live HTTP for production BVN/NIN checks.');
            }

            return back()->with('success', 'Dojah credentials configured for live BVN/NIN verification.');
        }

        if ($integration === 'remita') {
            if (! $this->settings->isRemitaReady()) {
                return back()->with('error', 'Add Remita merchant ID and API key before going live.');
            }

            return back()->with('success', 'Remita credentials configured.');
        }

        return back()->with('error', 'Connection test is not available for this integration yet.');
    }

    public function syncStaticDeposits(Request $request): RedirectResponse
    {
        $result = $this->staticAccounts->syncAllActive();

        if ($result['error'] !== null) {
            return back()->with('error', 'VA deposit sync failed (driver='.$result['driver'].'): '.$result['error']);
        }

        return back()->with(
            'success',
            "VA deposit sync complete (driver={$result['driver']}): credited {$result['credited']} payment(s) across {$result['accounts']} account(s).",
        );
    }
}
