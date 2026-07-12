<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Callback\Models\Callback;
use App\Domain\Fraud\Models\FraudAlert;
use App\Domain\Payments\Models\Deposit;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Settings\Models\AdminAuditLog;
use App\Domain\Settings\Models\PlatformSetting;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Domain\Support\Enums\SupportTicketStatus;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Wallet\Models\Wallet;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'wallets' => Wallet::query()->count(),
                'deposits_today' => Deposit::query()->whereDate('created_at', today())->count(),
                'open_callbacks' => Callback::query()->where('status', 'pending')->count(),
                'open_recoveries' => Recovery::query()->where('status', 'held')->count(),
                'fraud_alerts' => FraudAlert::query()->where('status', 'open')->count(),
                'open_support_tickets' => SupportTicket::query()
                    ->whereIn('status', [SupportTicketStatus::Open, SupportTicketStatus::Escalated])
                    ->count(),
            ],
            'integrations' => [
                'alatpay' => [
                    'ready' => $this->settings->isIntegrationReady('alatpay'),
                    'driver' => config('services.alatpay.driver'),
                    'subtitle' => 'Collections & BVN verification',
                    'bvn_ready' => $this->settings->isBvnVerificationReady(),
                ],
                'paystack' => [
                    'ready' => $this->settings->isIntegrationReady('paystack'),
                    'driver' => config('services.paystack.driver'),
                    'subtitle' => 'Bank withdrawals (Transfers)',
                ],
                'interswitch' => [
                    'ready' => $this->settings->isIntegrationReady('interswitch'),
                    'driver' => config('services.interswitch.driver'),
                    'subtitle' => 'Bills & airtime',
                ],
                'giglogistics' => [
                    'ready' => $this->settings->isIntegrationReady('giglogistics'),
                    'driver' => config('services.giglogistics.driver'),
                    'subtitle' => 'Marketplace shipping',
                ],
                'dojah' => [
                    'ready' => $this->settings->isDojahReady(),
                    'driver' => config('services.dojah.driver'),
                    'subtitle' => 'Tier 3 NIN (optional)',
                ],
                'remita' => [
                    'ready' => $this->settings->isRemitaReady(),
                    'driver' => config('services.remita.driver'),
                    'subtitle' => 'RRR payments',
                ],
                'termii' => [
                    'ready' => $this->settings->isTermiiReady(),
                    'driver' => config('services.termii.driver'),
                    'subtitle' => 'SMS & WhatsApp',
                ],
                'bridgecard' => [
                    'ready' => $this->settings->isVirtualCardsReady(),
                    'driver' => config('services.bridgecard.driver'),
                    'subtitle' => 'Virtual cards',
                ],
                'mail' => [
                    'ready' => (bool) config('reton.mail.notifications_enabled'),
                    'driver' => config('mail.default'),
                    'subtitle' => 'Email notifications',
                ],
                'bvn' => [
                    'provider' => $this->settings->bvnProviderLabel(),
                    'ready' => $this->settings->isBvnVerificationReady(),
                ],
            ],
            'recentAudit' => AdminAuditLog::query()
                ->with('user:id,name,email')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->map(fn (AdminAuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'group' => $log->group,
                    'user_name' => $log->user?->name,
                    'created_at' => $log->created_at,
                ]),
            'goLive' => $this->goLiveChecklist(),
            'queues' => [
                'support' => SupportTicket::query()
                    ->whereIn('status', [SupportTicketStatus::Open, SupportTicketStatus::Escalated])
                    ->count(),
                'fraud' => FraudAlert::query()->where('status', 'open')->count(),
                'callbacks' => Callback::query()->where('status', 'pending')->count(),
                'recoveries' => Recovery::query()->whereIn('status', ['held', 'escalated'])->count(),
            ],
        ]);
    }

    /**
     * @return list<array{id: string, label: string, ready: bool, href: string, detail: string}>
     */
    private function goLiveChecklist(): array
    {
        $adminPath = '/'.trim((string) config('reton.admin.path', 'admin'), '/');

        return [
            [
                'id' => 'alatpay',
                'label' => 'ALATPay collections',
                'ready' => $this->settings->isIntegrationReady('alatpay'),
                'href' => $adminPath.'/integrations',
                'detail' => 'API key, merchant login, business ID',
            ],
            [
                'id' => 'bvn',
                'label' => 'BVN verification',
                'ready' => $this->settings->isBvnVerificationReady(),
                'href' => $adminPath.'/integrations',
                'detail' => 'Required for permanent deposit accounts',
            ],
            [
                'id' => 'paystack',
                'label' => 'Paystack withdrawals',
                'ready' => $this->settings->isIntegrationReady('paystack'),
                'href' => $adminPath.'/integrations',
                'detail' => 'Secret key + Transfers enabled',
            ],
            [
                'id' => 'termii',
                'label' => 'Termii SMS',
                'ready' => $this->settings->isTermiiReady(),
                'href' => $adminPath.'/integrations',
                'detail' => 'OTP + transaction alerts',
            ],
            [
                'id' => 'https',
                'label' => 'Force HTTPS',
                'ready' => (bool) config('reton.security.force_https', false),
                'href' => $adminPath.'/site',
                'detail' => 'Site → Security before production traffic',
            ],
            [
                'id' => 'fees',
                'label' => 'Platform fees configured',
                'ready' => PlatformSetting::query()->whereKey('fees')->exists(),
                'href' => $adminPath.'/platform',
                'detail' => 'Save Admin → Platform → Fees at least once (defaults stay free until you set rates)',
            ],
        ];
    }
}
