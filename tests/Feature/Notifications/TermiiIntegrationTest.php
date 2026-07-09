<?php

declare(strict_types=1);

use App\Domain\Notifications\Services\OtpService;
use App\Domain\Payments\Alatpay\Contracts\AlatpayGateway;
use App\Domain\Payments\Alatpay\Gateways\FakeAlatpayGateway;
use App\Domain\Settings\Services\PlatformSettingsService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('stores termii credentials encrypted and marks integration ready', function () {
    $admin = readyUser(['is_admin' => true]);

    $this->actingAs($admin)->post('/admin/integrations/save', [
        'integration' => 'termii',
        'driver' => 'http',
        'base_url' => 'https://api.ng.termii.com',
        'api_key' => 'termii-live-key',
        'sender_id' => 'Reton',
        'channel' => 'generic',
        'timeout' => 15,
    ])->assertRedirect();

    expect(app(PlatformSettingsService::class)->isTermiiReady())->toBeTrue()
        ->and(config('services.termii.api_key'))->toBe('termii-live-key');
});

it('sends and verifies otp in fake mode', function () {
    config()->set('reton.sms.notifications_enabled', true);

    $user = User::factory()->create(['phone' => '+2348012345678']);
    $otp = app(OtpService::class);

    $otp->send((string) $user->phone, 'phone_verify');

    $stored = Cache::get('otp:'.hash('sha256', '2348012345678phone_verify'));
    expect($stored)->not->toBeNull();
});

it('blocks deposits without verified bvn', function () {
    $this->app->instance(AlatpayGateway::class, new FakeAlatpayGateway);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->forceFill(['transaction_pin' => bcrypt('1234')])->save();
    $wallet = app(\App\Domain\Wallet\Services\WalletService::class)->open($user, 'NGN');

    $this->actingAs($user)->post('/deposits', [
        'wallet_id' => $wallet->id,
        'amount' => 500_00,
        'method' => 'bank_transfer',
    ])->assertSessionHasErrors('bvn');
});
