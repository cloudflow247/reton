<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Auth\CountryDialCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function webRegisterPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ada',
        'middle_name' => 'Augusta',
        'last_name' => 'Lovelace',
        'email' => 'ada.web@retonpay.com',
        'country_iso' => 'NG',
        'country_code' => '234',
        'phone_national' => '8012345678',
        'password' => 'Sup3r-Secret!',
        'password_confirmation' => 'Sup3r-Secret!',
        'website' => '',
    ], $overrides);
}

it('renders the register page with country dial codes', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/Register')
            ->has('countries')
            ->where('countries.0.iso', 'NG'));
});

it('registers with split legal names and national phone plus country code', function () {
    $this->post('/register', webRegisterPayload())
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'ada.web@retonpay.com')->firstOrFail();

    expect($user->name)->toBe('Ada Augusta Lovelace')
        ->and($user->phone)->toBe('+2348012345678')
        ->and($user->country)->toBe('NG');
});

it('rejects registration when the honeypot is filled', function () {
    $this->from('/register')
        ->post('/register', webRegisterPayload([
            'website' => 'https://spam.example',
        ]))
        ->assertRedirect('/register')
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'ada.web@retonpay.com')->exists())->toBeFalse();
});

it('rejects login when the honeypot is filled', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    $this->from('/login')
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'website' => 'bot-filled',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('builds e164 phone numbers from dial codes', function () {
    expect(CountryDialCodes::toE164('234', '08012345678'))->toBe('+2348012345678')
        ->and(CountryDialCodes::toE164('1', '4155552671'))->toBe('+14155552671')
        ->and(count(CountryDialCodes::all()))->toBeGreaterThan(100);
});
