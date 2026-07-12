<?php

declare(strict_types=1);

use App\Domain\Callback\Enums\CallbackStatus;
use App\Domain\Callback\Models\Callback;
use App\Domain\Fraud\Enums\FraudAction;
use App\Domain\Fraud\Enums\FraudRiskLevel;
use App\Domain\Fraud\Models\FraudAlert;
use App\Domain\Recovery\Enums\RecoveryStatus;
use App\Domain\Recovery\Models\Recovery;
use App\Domain\Support\Enums\SupportTicketStatus;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\Enums\TransferType;
use App\Domain\Transfers\Models\Transfer;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function opsAdmin(): User
{
    return readyUser(['is_admin' => true]);
}

it('renders ops desks for admins', function (string $path, string $component) {
    $this->actingAs(opsAdmin())->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    'support' => ['/admin/support', 'Admin/Support'],
    'fraud' => ['/admin/fraud', 'Admin/Fraud'],
    'callbacks' => ['/admin/callbacks', 'Admin/Callbacks'],
    'recoveries' => ['/admin/recoveries', 'Admin/Recoveries'],
    'money' => ['/admin/money', 'Admin/Money'],
]);

it('resolves a support ticket from admin', function () {
    $admin = opsAdmin();
    $user = readyUser();

    $ticket = SupportTicket::query()->create([
        'reference' => 'TKT-'.Str::upper((string) Str::ulid()),
        'user_id' => $user->id,
        'subject' => 'Need help',
        'status' => SupportTicketStatus::Escalated,
        'note' => 'Please help',
    ]);

    $this->actingAs($admin)
        ->post("/admin/support/{$ticket->id}/resolve", ['note' => 'Handled'])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Resolved)
        ->and($ticket->fresh()->resolved_at)->not->toBeNull();
});

it('resolves a fraud alert from admin', function () {
    $admin = opsAdmin();
    $user = readyUser();

    $alert = FraudAlert::query()->create([
        'user_id' => $user->id,
        'action_context' => 'payout',
        'score' => 90,
        'level' => FraudRiskLevel::High,
        'recommended_action' => FraudAction::Freeze,
        'signals' => ['velocity'],
        'status' => 'open',
        'currency' => 'NGN',
    ]);

    $this->actingAs($admin)
        ->post("/admin/fraud/{$alert->id}/resolve", ['freeze_user' => true])
        ->assertRedirect();

    expect($alert->fresh()->status)->toBe('resolved')
        ->and($alert->fresh()->resolved_by)->toBe($admin->id)
        ->and($user->fresh()->status)->toBe('frozen');
});

it('shows user desk with wallets and kyc', function () {
    $admin = opsAdmin();
    $user = readyUser();
    app(\App\Domain\Wallet\Services\WalletService::class)->open($user, 'NGN');

    $this->actingAs($admin)->get("/admin/users/{$user->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/UserShow')
            ->has('user')
            ->has('wallets')
            ->where('user.email', $user->email));
});

it('includes go-live checklist on the admin dashboard', function () {
    $this->actingAs(opsAdmin())->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->has('goLive')
            ->has('queues')
            ->has('stats.open_support_tickets'));
});

it('forbids non-admins from ops desks', function () {
    $user = readyUser();

    $this->actingAs($user)->get('/admin/support')->assertForbidden();
    $this->actingAs($user)->get('/admin/money')->assertForbidden();
});
