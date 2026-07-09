<?php

declare(strict_types=1);

use App\Domain\Kyc\Models\KycVerificationLog;
use App\Domain\Kyc\Services\KycService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.dojah.driver' => 'fake']);
});

function kycUser(string $name = 'Reton Test User'): User
{
    return User::factory()->create([
        'name' => $name,
        'transaction_pin' => Hash::make('1234'),
    ]);
}

it('verifies bvn via dojah fake before tier 2 upgrade', function () {
    $user = kycUser();

    $kyc = app(KycService::class)->upgradeToTier2($user, '22334455667', '1990-05-15', '127.0.0.1');

    expect($kyc->tier->value)->toBe(2)
        ->and(KycVerificationLog::query()->where('user_id', $user->id)->where('status', 'success')->count())->toBe(1);
});

it('rejects bvn when date of birth does not match dojah record', function () {
    $user = kycUser();

    app(KycService::class)->upgradeToTier2($user, '22334455667', '1999-01-01');
})->throws(Illuminate\Validation\ValidationException::class);

it('rejects bvn when profile name does not match registry name', function () {
    $user = kycUser('Ada Obi');

    app(KycService::class)->upgradeToTier2($user, '22334455667', '1990-05-15');
})->throws(Illuminate\Validation\ValidationException::class);

it('requires identity consent on tier 2 web upgrade', function () {
    $user = kycUser();

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22334455667',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => false,
    ])->assertSessionHasErrors('identity_consent');
});

it('completes tier 2 via web with consent', function () {
    $user = kycUser();

    $this->actingAs($user)->post('/profile/kyc/tier-2', [
        'bvn' => '22334455667',
        'date_of_birth' => '1990-05-15',
        'identity_consent' => true,
    ])->assertRedirect(route('profile'))
        ->assertSessionHas('success');
});

it('verifies nin via dojah fake before tier 3 upgrade', function () {
    $user = kycUser();
    app(KycService::class)->upgradeToTier2($user, '22334455667', '1990-05-15');

    $kyc = app(KycService::class)->upgradeToTier3($user, '12345678901', '12 Admiralty Way', 'Lekki', 'Lagos', '127.0.0.1');

    expect($kyc->tier->value)->toBe(3)
        ->and(KycVerificationLog::query()->where('user_id', $user->id)->where('type', 'nin')->where('status', 'success')->exists())->toBeTrue();
});

it('rate limits kyc upgrade attempts', function () {
    $user = kycUser();

    $last = null;
    for ($i = 0; $i < 7; $i++) {
        $last = $this->actingAs($user)->post('/profile/kyc/tier-2', [
            'bvn' => '22334455667',
            'date_of_birth' => '1990-05-15',
            'identity_consent' => true,
        ]);
    }

    $last->assertStatus(429);
});
