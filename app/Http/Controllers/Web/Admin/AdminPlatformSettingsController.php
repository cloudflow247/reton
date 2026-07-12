<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Domain\Settings\Services\PlatformSettingsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPlatformSettingsController extends Controller
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Platform', [
            'groups' => [
                'kyc' => $this->settings->runtimeGroup('kyc'),
                'pin' => $this->settings->maskedGroup('pin'),
                'callback' => $this->settings->maskedGroup('callback'),
                'recovery' => $this->settings->maskedGroup('recovery'),
                'digital' => $this->settings->maskedGroup('digital'),
                'physical' => $this->settings->maskedGroup('physical'),
                'fraud' => $this->settings->maskedGroup('fraud'),
                'fx' => $this->settings->maskedGroup('fx'),
                'cards' => $this->settings->maskedGroup('cards'),
                'bills' => $this->settings->maskedGroup('bills'),
                'payouts' => $this->settings->maskedGroup('payouts'),
                'features' => $this->settings->maskedGroup('features'),
                'fees' => $this->settings->maskedGroup('fees'),
                'horizon' => $this->settings->maskedGroup('horizon'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'group' => ['required', 'in:kyc,pin,callback,recovery,digital,physical,fraud,fx,cards,bills,payouts,features,fees,horizon'],
        ]);

        $group = (string) $request->input('group');

        $feeField = fn (string $key): array => [$key => ['required', 'integer', 'min:0', 'max:10000000']];

        $rules = match ($group) {
            'kyc' => [
                'tier1_single_max' => ['required', 'integer', 'min:10000', 'max:10000000000'],
                'tier1_daily_in_max' => ['required', 'integer', 'min:10000', 'max:50000000000'],
                'tier1_balance_max' => ['required', 'integer', 'min:10000', 'max:50000000000'],
                'tier2_single_max' => ['required', 'integer', 'min:10000', 'max:50000000000'],
                'tier2_daily_in_max' => ['required', 'integer', 'min:10000', 'max:200000000000'],
                'tier2_balance_max' => ['required', 'integer', 'min:10000', 'max:200000000000'],
                'tier3_single_max' => ['required', 'integer', 'min:10000', 'max:500000000000'],
                'tier3_daily_in_max' => ['required', 'integer', 'min:10000', 'max:2000000000000'],
                'tier3_balance_max' => ['required', 'integer', 'min:10000', 'max:5000000000000'],
            ],
            'pin' => [
                'max_attempts' => ['required', 'integer', 'min:3', 'max:20'],
                'lockout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            ],
            'callback' => [
                'hold_hours' => ['required', 'integer', 'min:1', 'max:720'],
                'response_hours' => ['required', 'integer', 'min:1', 'max:168'],
                'unanswered_resolution' => ['required', 'in:refund,release'],
            ],
            'recovery' => [
                'report_window_hours' => ['required', 'integer', 'min:1', 'max:168'],
                'response_hours' => ['required', 'integer', 'min:1', 'max:168'],
                'fee_bps' => ['required', 'integer', 'min:0', 'max:5000'],
            ],
            'digital' => [
                'confirm_hours' => ['required', 'integer', 'min:1', 'max:720'],
                'delivery_deadline_hours' => ['required', 'integer', 'min:1', 'max:720'],
                'dispute_grace_hours' => ['required', 'integer', 'min:0', 'max:168'],
            ],
            'physical' => [
                'ship_deadline_hours' => ['required', 'integer', 'min:1', 'max:720'],
                'confirm_hours' => ['required', 'integer', 'min:1', 'max:720'],
                'dispute_grace_hours' => ['required', 'integer', 'min:0', 'max:168'],
                'verification_pass_score' => ['required', 'integer', 'min:0', 'max:100'],
                'hub_verification_pass_score' => ['required', 'integer', 'min:0', 'max:100'],
                'default_hub_name' => ['required', 'string', 'max:200'],
                'default_hub_line1' => ['required', 'string', 'max:200'],
                'default_hub_city' => ['required', 'string', 'max:100'],
                'default_hub_state' => ['required', 'string', 'max:100'],
                'default_hub_phone' => ['required', 'string', 'max:40'],
            ],
            'fraud' => [
                'velocity_window_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
                'velocity_max_count' => ['required', 'integer', 'min:1', 'max:100'],
                'velocity_points' => ['required', 'integer', 'min:0', 'max:100'],
                'large_amount_threshold' => ['required', 'integer', 'min:10000', 'max:50000000000'],
                'large_amount_points' => ['required', 'integer', 'min:0', 'max:100'],
                'new_device_points' => ['required', 'integer', 'min:0', 'max:100'],
                'failed_pin_threshold' => ['required', 'integer', 'min:1', 'max:20'],
                'failed_pin_points' => ['required', 'integer', 'min:0', 'max:100'],
                'new_beneficiary_points' => ['required', 'integer', 'min:0', 'max:100'],
                'medium_min' => ['required', 'integer', 'min:0', 'max:100'],
                'high_min' => ['required', 'integer', 'min:0', 'max:100'],
                'escalate_min' => ['required', 'integer', 'min:0', 'max:100'],
            ],
            'fx' => [
                'usd_ngn_rate' => ['required', 'numeric', 'min:1', 'max:100000'],
                'spread_bps' => ['required', 'integer', 'min:0', 'max:5000'],
            ],
            'cards' => [
                'provider' => ['required', 'in:bridgecard'],
                'min_funding_ngn' => ['required', 'integer', 'min:100', 'max:1000000000'],
                'min_funding_usd' => ['required', 'integer', 'min:1', 'max:100000'],
                'default_usd_limit' => ['required', 'string', 'max:20'],
            ],
            'bills' => [
                'provider' => ['required', 'in:interswitch,remita'],
            ],
            'payouts' => [
                'provider' => ['required', 'in:paystack,alatpay'],
            ],
            'features' => [
                'withdraw' => ['required', 'boolean'],
                'bills' => ['required', 'boolean'],
                'cards' => ['required', 'boolean'],
                'checkout' => ['required', 'boolean'],
                'card_pay' => ['required', 'boolean'],
                'one_time' => ['required', 'boolean'],
                'physical_listings' => ['required', 'boolean'],
            ],
            'fees' => array_merge(
                $feeField('transfer_instant_bps'),
                $feeField('transfer_instant_flat_minor'),
                $feeField('transfer_protected_bps'),
                $feeField('transfer_protected_flat_minor'),
                $feeField('withdraw_bps'),
                $feeField('withdraw_flat_minor'),
                $feeField('deposit_bps'),
                $feeField('deposit_flat_minor'),
                $feeField('callback_bps'),
                $feeField('callback_flat_minor'),
                $feeField('listing_publish_bps'),
                $feeField('listing_publish_flat_minor'),
                $feeField('marketplace_sale_bps'),
                $feeField('marketplace_sale_flat_minor'),
                $feeField('recovery_bps'),
                $feeField('recovery_flat_minor'),
                $feeField('sms_alert_bps'),
                $feeField('sms_alert_flat_minor'),
            ),
            'horizon' => [
                'allowed_emails' => ['nullable', 'string', 'max:2000'],
            ],
        };

        $validated = $request->validate(array_merge(
            ['group' => ['required', 'in:kyc,pin,callback,recovery,digital,physical,fraud,fx,cards,bills,payouts,features,fees,horizon']],
            $rules,
        ));

        unset($validated['group']);

        $this->settings->updateGroup($group, $validated, $request->user(), $request->ip());

        return back()->with('success', ucfirst($group).' settings saved and applied.');
    }
}
